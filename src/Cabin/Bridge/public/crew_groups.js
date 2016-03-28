$(document).ready(function() {
    // Work around webkit browsers' inability to style select boxes
    if ($.browser.webkit) {
        $("#groups_parents").children("option").each(function () {
            $(this).html($(this).data('fullpath'));
        });
    }
});