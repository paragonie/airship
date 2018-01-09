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

    var slug_el = $("#blog_category_slug");
    originalSlug = slug_el.data('original');
    slug_el.on('change', function() {
        toggleSlugCheckbox();
    });
    toggleSlugCheckbox();
});