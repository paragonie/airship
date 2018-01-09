function toggleSlugCheckbox()
{
    if ($("#author_slug").val() !== originalSlug) {
        $("#author_slug_checkbox_wrapper").show();
    } else {
        $("#author_slug_checkbox_wrapper").hide();
    }
}
var originalSlug = '';
$(document).ready(function() {
    var slug_el = $("#author_slug");
    originalSlug = slug_el.data('original');
    slug_el.on('change', function() {
        toggleSlugCheckbox();
    });
    toggleSlugCheckbox();
});
