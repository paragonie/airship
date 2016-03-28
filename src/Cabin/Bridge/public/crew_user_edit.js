$(document).ready(function() {
    $("#password").change(function() {
        var zx = zxcvbn($("#password").val());
        passwordWarning(zx.feedback.warning, zx.score);
    });

    // Work around webkit browsers' inability to style select boxes
    if ($.browser.webkit) {
        $("#users_groups").children("option").each(function () {
            $(this).html($(this).data('fullpath'));
        });
    }
});