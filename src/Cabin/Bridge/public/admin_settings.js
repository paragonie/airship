var ledger_defaults = {
    "file": "~/tmp/logs",
    "database": "airship_logs"
};
var old_driver = "";

$(document).ready(function() {
    $("#admin_settings_accordion").accordion({
        heightStyle: "content"
    });
    $("#ledger_driver").on('change', function(e) {
        var new_driver = $("#ledger_driver").val();
        // Update label
        if (new_driver === "file") {
            $("#ledger_details_label").html("Log Directory:");
            $("#ledger_details").attr('name', 'universal[ledger][path]');
        } else {
            $("#ledger_details_label").html("Database Table:");
            $("#ledger_details").attr('name', 'universal[ledger][table]');
        }

        // Swap out details:
        ledger_defaults[old_driver] = $("#ledger_details").val();
        $("#ledger_details").val(
            ledger_defaults[new_driver]
        );

        // Update the reference to the current driver
        old_driver = new_driver;
    });
});