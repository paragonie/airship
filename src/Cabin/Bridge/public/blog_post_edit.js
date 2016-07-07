function toggleSlugCheckbox()
{
    if ($("#blog_post_slug").val() !== originalSlug) {
        $("#blog_post_slug_checkbox_wrapper").show();
    } else {
        $("#blog_post_slug_checkbox_wrapper").hide();
    }
}
var originalSlug = '';

$(document).ready(function() {
    $("#blog_post_category").children("option").each(function () {
        $(this).html($(this).data('fullpath'));
    });

    $("#blog_posts_metadata").hide(0);
    $("#show_metadata").on('change', function() {
        if ($("#show_metadata").is(":checked")) {
            $("#blog_posts_metadata").show(100);
        } else {
            $("#blog_posts_metadata").hide(100);
        }
    });

    originalSlug = $("#blog_post_slug").data('original');
    $("#blog_post_slug").on('change', function() {
        toggleSlugCheckbox();
    });
    toggleSlugCheckbox();
});