var main_branch;
var indicator = {
    "admin": "<i class='fa fa-bolt perm-admin'></i>",
    "on": "<i class='fa fa-check perm-on'></i>",
    "inherited": "<i class='fa fa-level-up fa-flip-horizontal perm-inherits'></i>",
    "off": "<i class='fa fa-close perm-off''></i>"
};

function toggleUserCheckbox() {
    var userid = $(this).data('id');
    var action = $(this).data('action');
    if ($(this).is(':checked')) {
        $("#user_" + userid + "_" + action + "_indicator").html(
            indicator.on
        );
        $("#user_" + userid + "_" + action + "_indicator").parent()
            .addClass('permtable_on')
            .removeClass('permtable_off');
    } else {
        $("#user_" + userid + "_" + action + "_indicator").html(
            indicator.off
        );
        $("#user_" + userid + "_" + action + "_indicator").parent()
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
        if ($('#'+$(this).attr('id')+'_indicator').data('state') === 'admin') {
            $('#'+$(this).attr('id')+'_cbox')
                .prop('disabled', true)
                .prop('checked', false);
            $('#'+$(this).attr('id')+'_indicator')
                .html(indicator.admin);
            $('#'+$(this).attr('id')+'_indicator').parent()
                .removeClass('permtable_on')
                .removeClass('permtable_off')
                .removeClass('permtable_inherits')
                .addClass('permtable_admin');
        } else if (inherit[lbl]) {
            $('#'+$(this).attr('id')+'_cbox').attr('disabled', 'disabled');
            $('#'+$(this).attr('id')+'_indicator')
                .html(indicator.inherited);

            $('#'+$(this).attr('id')+'_indicator').parent()
                .removeClass('permtable_on')
                .removeClass('permtable_off')
                .addClass('permtable_inherits')
                .removeClass('permtable_admin');
        } else if ($('#'+$(this).attr('id')+'_cbox').is(':checked')) {
            if (inherit[lbl]) {
                $('#'+$(this).attr('id')+'_cbox').attr('disabled', 'disabled');
            } else {
                $('#'+$(this).attr('id')+'_cbox').removeAttr('disabled');
            }
            inherit[lbl] = true;
            $('#'+$(this).attr('id')+'_indicator')
                .html(indicator.on);
            $('#'+$(this).attr('id')+'_indicator').parent()
                .addClass('permtable_on')
                .removeClass('permtable_off')
                .removeClass('permtable_inherits')
                .removeClass('permtable_admin');
        } else {
            $('#'+$(this).attr('id')+'_cbox').removeAttr('disabled');
            $('#'+$(this).attr('id')+'_indicator')
                .html(indicator.off);

            $('#'+$(this).attr('id')+'_indicator').parent()
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
    console.log('end_redraw');
}

function get_branch(id) {
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
        if (_myParent == null) {
            main_branch.push(_myId);
        } else {
            var parents = [_myParent];
            var curr = _myParent;
            while (curr != null && curr != 0) {
                curr = $("#group_" + curr).data('parent');
                if (curr == 0 || curr == null) {
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
            "username": $("#newUserField").val()
        };
        if ($("#perm_username_" + req.username).length) {
            alert(req.username + " is already present in the list above");
            return;
        }

        console.log(req);
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