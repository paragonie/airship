/**
 * CMS Airship - Collapsing Bridge Menu
 *
 * @since v1.3.0
 */
var bridgeLeftMenu = {
    "init": function (link, menu) {
        bridgeLeftMenu.initLink(link);
        bridgeLeftMenu.initMenu(menu);
    },

    "initLink": function(active) {
        var activeList = bridgeLeftMenu.getList(active);
        $(".bridge-menu a").each(function () {
            var section = $(this).attr('id');
            if (typeof section === 'string') {
                if ($.inArray(section, activeList) > -1) {
                    $(this).addClass('bridge-left-active');
                }
            }
        });
    },

    "initMenu": function(active) {
        var activeList = bridgeLeftMenu.getList(active);
        $(".bridge-collapse").each(function () {
            var section = $(this).data('section');
            if ($.inArray(section, activeList) === -1) {
                // Hide the child <ul>
                $(this).parent().find("ul").hide();
                $(this).parent().removeClass('collapse-active-container');
                $(this).removeClass('collapse-active');
            } else {
                $(this).parent().addClass('collapse-active-container');
                $(this).addClass('collapse-active');
            }
            $(this).attr('href', '#left-' + $(this).data('section').toLowerCase());
            $(this).on('click', function () {
                $(this).toggleClass('collapse-active-container');
                $(this).parent().children("ul").toggle();
                $(this).toggleClass('collapse-active');
            });
        });
    },

    "getList": function (active) {
        if (typeof(active) === "undefined") {
            return [];
        } else if (typeof(active) === "array" || typeof(active) === "object") {
            return active;
        } else if (typeof(active) === "string") {
            if (active === "") {
                return [];
            } else {
                var activeList = $.parseJSON(active);
                if (typeof(activeList) !== "array") {
                    activeList = [activeList];
                }
            }
            return activeList;
        } else {
            return [];
        }
    }
};

$(document).ready(function () {
    return bridgeLeftMenu.init(
        $("body").data('activelink'),
        $("body").data('activesubmenu')
    )
});
