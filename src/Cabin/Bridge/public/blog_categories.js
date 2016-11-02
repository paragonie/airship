function toggleSlugCheckbox()
{
    if ($("#blog_category_slug").val() !== originalSlug) {
        $("#blog_category_slug_checkbox_wrapper").show();
    } else {
        $("#blog_category_slug_checkbox_wrapper").hide();
    }
}
var originalSlug = '';
$(document).ready(function() {
    $("#parent").children("option").each(function () {
        $(this).html($(this).data('fullpath'));
    });

    originalSlug = $("#blog_category_slug").data('original');
    $("#blog_category_slug").on('change', function() {
        toggleSlugCheckbox();
    });
    toggleSlugCheckbox();
});