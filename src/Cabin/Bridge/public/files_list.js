$(document).ready(function() {
    $("#files_list_cabin").on('change', function() {
        var cabin = $(this).val()
            .replace("/", "");
        window.location = $("#bridge_main_menu_left").data('linkprefix') + "/" +
            $("#bridge_files_navigation").data('middle') + "/" +
            cabin;
    });

    $("#add-files").click(function() {
        $("#new-file-wrapper").append(
            '<li><input type="file" name="new_files[]" /></li>'
        );
    });
});