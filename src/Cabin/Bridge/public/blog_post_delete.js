$(document).ready(function() {
    $("#blog_post_delete_redirect_box").hide();
    $("#create_redirect").on('change', function() {
        if ($(this).is(':checked')) {
            $("#blog_post_delete_redirect_box").show(200);
            $("#redirect_to").prop("required", true);
        } else {
            $("#blog_post_delete_redirect_box").hide(200);
            $("#redirect_to").prop("required", false);
        }
    });
    $("#cancel_btn").click(function() {
        window.location=$(this).data('href');
    });
});