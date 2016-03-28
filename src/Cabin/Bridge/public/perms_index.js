$(document).ready(function() {
    $("#test_url_btn").click(function() {
        return $.post(
            $("#bridge_main_menu_left").data('linkprefix') + "/ajax/perm_test",
            {
                "url": $("#test_url").val()
            },
            function (result) {
                $("#perms-test-results").html(result);
            }
        );
    });
});