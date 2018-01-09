var ledger_defaults = {
    "file": "~/tmp/logs",
    "database": "airship_logs"
};
var old_driver = "";

/**
 * Configuration toggle with a control element.
 */
var configToggle = {
    "togglers": {},

    "init": function (table, controller) {
        configToggle.togglers[controller] = table;
        configToggle.toggle(controller);
        $(controller).on('change', configToggle.onChangeHandler);
    },

    "onChangeHandler": function (e) {
        var _id = '#' + $(this).attr('id');
        if (typeof configToggle.togglers[_id] === 'string') {
            configToggle.toggle(_id);
        }
    },

    "toggle": function(_id) {
        var value = $(_id).val();
        $(configToggle.togglers[_id]).find('.config-email-toggled').each(
            function (index, element) {
                var tr = $(this).data('transport');
                console.log(tr);
                if (!tr) {
                    // Do nothing.
                    return;
                }
                if (tr.indexOf(value) >= 0) {
                    $(this).show('fast');
                } else {
                    $(this).hide('fast');
                }
            }
        );
    }
};

$(document).ready(function() {
    $("#admin_settings_accordion").accordion({
        heightStyle: "content"
    });
    $("#ledger_driver").on('change', function(e) {
        var details_el = $("#ledger_details");
        var new_driver = $("#ledger_driver").val();
        // Update label
        if (new_driver === "file") {
            $("#ledger_details_label").html("Log Directory:");
            details_el.attr('name', 'universal[ledger][path]');
        } else {
            $("#ledger_details_label").html("Database Table:");
            details_el.attr('name', 'universal[ledger][table]');
        }

        // Swap out details:
        ledger_defaults[old_driver] = details_el.val();
        details_el.val(ledger_defaults[new_driver]);

        // Update the reference to the current driver
        old_driver = new_driver;
    });
    configToggle.init("#email_config_table", "#email_transport");
});