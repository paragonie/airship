var main_branch;
var indicator = {
    "admin": "<i class='fa fa-bolt perm-admin'></i>",
    "on": "<i class='fa fa-check perm-on'></i>",
    "inherited": "<i class='fa fa-level-up fa-flip-horizontal perm-inherits'></i>",
    "off": "<i class='fa fa-close perm-off''></i>"
};

function toggleUserCheckbox() {
    var userid = Airship.filter($(this).data('id'), Airship.FILTER_ID);
    var action = Airship.filter($(this).data('action'));

    var el = $("#user_" + userid + "_" + action + "_indicator");
    if ($(this).is(':checked')) {
        el.html(indicator.on);
        el.parent()
            .addClass('permtable_on')
            .removeClass('permtable_off');
    } else {
        el.html(indicator.off);
        el.parent()
            .addClass('permtable_off')
            .removeClass('permtable_on');
    }
}

function get_direct_children(_myId) {
    var children = [];
    $('.perm_row').each(function() {
        var _myParent = $(this).data('parent') || null;
        if (_myParent === _myId) {
            children.push($(this).data('id'));
        }
    });
    return children;
}

function get_all_children(_myId) {
    var children = [];
    $('.perm_row').each(function() {
        var _myParent = $(this).data('parent') || null;
        if (_myParent === _myId) {
            children.push($(this).data('id'));
            var grandchildren = get_all_children($(this).data('id')) || [];
            children.push.apply(children, grandchildren);
        }
    });
    return children;
}

function redraw_branch(id) {
    console.log('begin_redraw');
    if (id < 1) {
        return false;
    }
    var inherit = $.extend({}, arguments[1] || {});
    $("#group_"+id+" .permtable_perm").each(function() {
        var lbl = $(this).data('label');
        if (typeof inherit[lbl] === 'undefined') {
            inherit[lbl] = false;
        }
        var ind_el = $('#'+$(this).attr('id')+'_indicator');
        var cbox_el = $('#'+$(this).attr('id')+'_cbox');
        if (ind_el.data('state') === 'admin') {
            cbox_el
                .prop('disabled', true)
                .prop('checked', false);
            ind_el
                .html(indicator.admin);
            ind_el.parent()
                .removeClass('permtable_on')
                .removeClass('permtable_off')
                .removeClass('permtable_inherits')
                .addClass('permtable_admin');
        } else if (inherit[lbl]) {
            cbox_el.attr('disabled', 'disabled');
            ind_el.html(indicator.inherited);

            ind_el.parent()
                .removeClass('permtable_on')
                .removeClass('permtable_off')
                .addClass('permtable_inherits')
                .removeClass('permtable_admin');
        } else if (cbox_el.is(':checked')) {
            if (inherit[lbl]) {
                cbox_el.attr('disabled', 'disabled');
            } else {
                cbox_el.removeAttr('disabled');
            }
            inherit[lbl] = true;
            ind_el.html(indicator.on);
            ind_el.parent()
                .addClass('permtable_on')
                .removeClass('permtable_off')
                .removeClass('permtable_inherits')
                .removeClass('permtable_admin');
        } else {
            cbox_el.removeAttr('disabled');
            ind_el.html(indicator.off);

            ind_el.parent()
                .removeClass('permtable_on')
                .addClass('permtable_off')
                .removeClass('permtable_inherits')
                .removeClass('permtable_admin');
        }
        //console.log($(this).attr('id'));
    });
    var kids = get_direct_children(id);
    for (var i = 0; i < kids.length; i++) {
        redraw_branch(kids[i], inherit);
    }
}

function get_branch(id) {
    // Filter the ID to prevent DOM-based abuse:
    id = Airship.filter(id, Airship.FILTER_ID);

    console.log('begin_get_branch_'+id);
    var list = $("#group_"+id).data('ancestors');
    if (typeof list === 'undefined') {
        return id;
    }
    if (typeof list === 'string') {
        if (list.length > 0) {
            list = list.split('|');
        }
    }
    for (var i = 0; i < list.length; i++) {
        var li = parseInt(list[i], 10);
        var idx = main_branch.indexOf(li);
        if (idx !== -1) {
            // This is a main_branch entry
            return li;
        }
    }
    return id;
}

$(document).ready(function() {
    $('.permtable_perm').hover(
        function() {
            preview_branch(
                $(this).parent().data('id'),
                $(this).data('label')
            );
        },
        function() {
            redraw_branch(
                get_branch($(this).parent().data('id'))
            );
        }
    );

    $('.perms_checkbox').on('change', function() {
        redraw_branch(
            get_branch($(this).data('id'))
        );
    });
    $('.perms_user_checkbox').on('change', toggleUserCheckbox);

    main_branch = [];

    // Let's calculate each child/parent
    $('.perm_row').each(function() {
        var _myId = $(this).data('id');
        var _myParent = $(this).data('parent') || null;
        if (_myParent === null) {
            main_branch.push(_myId);
        } else {
            var parents = [_myParent];
            var curr = _myParent;
            while (curr !== null && curr !== 0) {
                curr = $("#group_" + curr).data('parent');
                if (curr === 0 || curr === null) {
                    break;
                }
                parents.push(curr);
            }
            $(this).data('ancestors', parents.join('|'));
        }
        $(this).data('children', get_all_children(_myId));
    });

    for (var i = 0; i < main_branch.length; i++) {
        redraw_branch(main_branch[i]);
    }

    $("#add_user_btn").click(function() {
        var req = {
            "cabin": $("#cabin").val(),
            "context": $("#contextId").val(),
            "username": $("#newUserField").val(),
            "csrf_token": $("body").data('ajaxtoken')
        };
        if ($("#perm_username_" + req.username).length) {
            alert(req.username + " is already present in the list above");
            return;
        }

        var to = $("#bridge_main_menu_left").data('linkprefix') + "/ajax/get_perms_user";
        $.post(to, req, function (data) {
            if (data.status === 'OK') {
                $("#perms_userlist tbody").append(data.message);
                $("#perms_userlist tbody .perms_user_checkbox").on('change', toggleUserCheckbox);
            } else {
                alert(data.message);
            }
        });
    });
});