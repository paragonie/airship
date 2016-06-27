$(document).ready(function() {
    $(".csp_add_btn").click(function () {
        var key = $(this).data('key');
        if (key === 'plugin-types') {
            var placeholder = 'application/javascript';
        } else {
            var placeholder = 'example.com';
        }
        $("#csp_" + key + "_whitelist").append(
            "<li><input \n" +
            "        class=\"full-width\"\n" +
            "        title=\"Whitelist\"\n" +
            "        type=\"text\"\n" +
            "        placeholder=\"" + placeholder + "\"\n" +
            "        name=\"content_security_policy[" + key + "][allow][]\"\n" +
            "    /></li>"
        );
    });

    $('.csp_disable_all').each(function () {
        var key = $(this).data('key');
        if ($(this).is(':checked')) {
            $("#csp_" + key + "_inner").hide('fast');
        } else {
            $("#csp_" + key + "_inner").show('fast');
        }
    });

    $('.csp_disable_all').click(function () {
        var key = $(this).data('key');
        if ($(this).is(':checked')) {
            $("#csp_" + key + "_inner").hide('fast');
        } else {
            $("#csp_" + key + "_inner").show('fast');
        }
    });
});