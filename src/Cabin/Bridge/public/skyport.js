/**
 * CMS Airship - Skyport
 */
var skyport = {
    /* Properties */
    "lastAjaxPage": [null, null],
    "page": null,
    "prefix": "",
    "query": "",

    /* Methods */
    "handleLeftLink": function() {
        var el = $(this);
        var action = $(this).data('id') || null;
        var type = $(this).data('type') || null;
        skyport.page = null;
        return skyport.loadAjaxPage(action, type);
    },

    "handleSearchChange": function() {
        skyport.query = $(this).val();
        if (skyport.lastAjaxPage[0] === 'browse') {
            skyport.loadAjaxPage(
                skyport.lastAjaxPage[0],
                skyport.lastAjaxPage[1]
            );
        }
        console.log(skyport.query);
    },

    "init": function() {
        skyport.prefix = $("#bridge_main_menu_left").data('linkprefix');
        $(".skyport-left-link").on('click', skyport.handleLeftLink);
        $("#skyport-search").on('change', skyport.handleSearchChange);
    },

    "loadAjaxPage": function(which, type) {
        var args = {};
        if (type !== null) {
            args["type"] = type;
        }
        if (skyport.query.length > 1) {
            // Search query
            args["query"] = skyport.query;
        }
        if (skyport.page !== null) {
            // Page number
            args["page"] = skyport.page;
        }
        $.post(
            skyport.prefix + 'ajax/admin/skyport/' + which,
            args,
            function (html) {
                skyport.lastAjaxPage = [which, type];
                $("#skyport-main").html(html);
                skyport.setupPageChangEvents();
            }
        );
    },

    "setupPageChangeEvents": function() {
        $(".skyport-page").on('click', function() {
            skyport.page = $(this).data('page');
            skyport.loadAjaxPage(
                skyport.lastAjaxPage[0],
                skyport.lastAjaxPage[1]
            );
        });
    }
};

$(document).ready(function() {
    skyport.init();
    skyport.loadAjaxPage("installed");
});