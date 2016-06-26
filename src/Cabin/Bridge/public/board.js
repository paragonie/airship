$(document).ready(function() {
    $("#optional_field_wrapper").hide(0);
    $("#show_hide_optional").on('change', function() {
        $("#optional_field_wrapper").toggle(300);
    });
    $("#username").change(function() {
        var API_ENDPOINT = window.location.pathname + "/checkUsername";
        $.post(
            API_ENDPOINT,
            {
                "username": $("#username").val()
            },
            function(r) {
                if (r.status !== "success") {
                    alert("Request failed!");
                } else if (!r.result.available) {
                    alert(r.message);
                }
            }
        );
    });
    $("#password").change(function() {
        var zx = zxcvbn($("#password").val());
        console.log(JSON.stringify(zx));
        passwordWarning(zx.feedback.warning, zx.score);
    });
});