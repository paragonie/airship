$(document).ready(function() {
    $("#pages_metadata").hide(0);
    $("#show_metadata").on('change', function() {
        if ($("#show_metadata").is(":checked")) {
            $("#pages_metadata").show(100);
        } else {
            $("#pages_metadata").hide(100);
        }
    });
});