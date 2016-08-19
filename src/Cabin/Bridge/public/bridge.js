var bridgeLeftMenu = {
    "active": "",

    "init": function (active) {
        if (typeof(active) === "undefined") {
            var activeList = [];
        } else if (typeof(active) === "array" || typeof(active) === "object") {
            activeList = active;
        } else if (typeof(active) === "string") {
            if (active === "") {
                activeList = [];
            } else {
                var activeList = $.parseJSON(active);
                if (typeof(activeList) !== "array") {
                    activeList = [activeList];
                }
            }
        } else {
            var activeList = [];
        }
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
    }
};

$(document).ready(function () {
    return bridgeLeftMenu.init(
        $("body").data('activesubmenu')
    )
});