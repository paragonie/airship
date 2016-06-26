$(document).ready(function() {
    $("#blog_post_category").children("option").each(function () {
        $(this).html($(this).data('fullpath'));
    });
});