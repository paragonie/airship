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

    var meta_el = $("#blog_posts_metadata");
    meta_el.hide(0);
    $("#show_metadata").on('change', function() {
        if ($(this).is(":checked")) {
            meta_el.show(100);
        } else {
            meta_el.hide(100);
        }
    });

    var slug_el = $("#blog_post_slug");
    originalSlug = slug_el.data('original');
    slug_el.on('change', function() {
        toggleSlugCheckbox();
    });
    toggleSlugCheckbox();
});
