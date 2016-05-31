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

    originalSlug = $("#blog_post_slug").data('original');
    $("#blog_post_slug").on('change', function() {
        toggleSlugCheckbox();
    });
    toggleSlugCheckbox();
});