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
    originalSlug = $("#author_slug").data('original');
    $("#author_slug").on('change', function() {
        toggleSlugCheckbox();
    });
    toggleSlugCheckbox();
});
