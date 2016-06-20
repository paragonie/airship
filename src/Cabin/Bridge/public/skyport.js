/**
 * CMS Airship - Skyport
 */
var skyport = {
    /* Properties */
    "lastAjaxPage": [null, null], // For updating the search query
    "page": null,                 // Current page number
    "prefix": "",                 // Cabin URL prefix
    "query": "",                  // Search Query
    "timeout": 900000,            // 900 seconds = 15 minutes

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
        skyport.setupLeftLinks();
    },

    "installPackage": function(type, supplier, pkg) {
        var password = arguments[3] || null;
        $.post(
            skyport.prefix + 'admin/skyport/install',
            {
                "type": type,
                "supplier": supplier,
                "package": pkg,
                "password": password
            },
            function (response) {
                if (response.status === 'PROMPT') {
                    return skyport.installPasswordPrompt(type, supplier, pkg);
                } else if (response.status === 'ERROR') {
                    alert(response.message);
                }
                // Otherwise, we assume we're doing fine.
                skyport.viewPackage(type, supplier, pkg);
            }
        );
    },

    "updatePackage": function(type, supplier, pkg, version) {
        if (!version) {
            return;
        }
        $.post(
            skyport.prefix + 'admin/skyport/update',
            {
                "type": type,
                "supplier": supplier,
                "package": pkg,
                "version": version
            },
            function (response) {
                if (response.status === 'ERROR') {
                    alert(response.message);
                }
                // Otherwise, we assume we're doing fine.
                skyport.viewPackage(type, supplier, pkg);
            }
        );
    },

    "installPasswordPrompt": function(type, supplier, pkg) {
        var password = prompt("Please enter the password to unlock the Skyport");
        if (!password) {
            return false;
        }
        return skyport.installPackage(type, supplier, pkg, password);
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
                skyport.setupPageChangeEvents();
            }
        );
    },

    "refreshLeftMenu": function() {
        $.get(
            skyport.prefix + 'ajax/admin/skyport/leftmenu',
            {},
            function (html) {
                $("#skyport-left").html(html);
                skyport.setupLeftLinks();
                setTimeout(skyport.refreshLeftMenu, skyport.timeout);
            }
        );
    },

    "refreshPackageInfo": function(type, supplier, pkg) {
        $.post(
            skyport.prefix + "ajax/admin/skyport/refresh",
            {
                "type": type,
                "supplier": supplier,
                "package": pkg
            },
            function(response) {
                if (response.status == "OK") {
                    skyport.viewPackage(type, supplier, pkg);
                }
            }
        );
    },

    "setupLeftLinks": function() {
        $(".skyport-left-link").on('click', skyport.handleLeftLink);
        $("#skyport-search").on('change', skyport.handleSearchChange);
    },

    "setupPageChangeEvents": function() {
        $(".skyport-page").on('click', function() {
            skyport.page = $(this).data('page');
            skyport.loadAjaxPage(
                skyport.lastAjaxPage[0],
                skyport.lastAjaxPage[1]
            );
        });
        $(".skyport-package-link").on('click', function() {
            skyport.viewPackage(
                $(this).data('type'),
                $(this).data('supplier'),
                $(this).data('package')
            )
        });
        $("#skyport-install").on('click', function() {
            skyport.installPackage(
                $(this).data('type'),
                $(this).data('supplier'),
                $(this).data('package')
            )
        });
        $("#skyport-upgrade-button").on('click', function() {
            skyport.updatePackage(
                $(this).data('type'),
                $(this).data('supplier'),
                $(this).data('package'),
                $("#skyport-upgrade-version").val()
            )
        });
        $("#skyport-refresh-package").on('click', function() {
            skyport.refreshPackageInfo(
                $(this).data('type'),
                $(this).data('supplier'),
                $(this).data('package')
            )
        });
    },

    "viewPackage": function(type, supplier, pkg) {
        $.post(
            skyport.prefix + "ajax/admin/skyport/view",
            {
                "type": type,
                "supplier": supplier,
                "package": pkg
            },
            function(html) {
                $("#skyport-main").html(html);
                skyport.setupPageChangeEvents();
            }
        );
    }
};

$(document).ready(function() {
    skyport.init();
    skyport.loadAjaxPage("installed");
    setTimeout(skyport.refreshLeftMenu, skyport.timeout);
});
