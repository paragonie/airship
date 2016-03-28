$(document).ready(function() {
    if ($.browser.webkit) {
        // Work around webkit being terrible and not letting us style <option> tags:
        $("#directory").children("option").each(function () {
            if ($(this).val() != parseInt($(this).val(), 10)) {
                $(this).html("-- " + $(this).val() + " --");
            } else {
                $(this).html($(this).data('fullpath'));
            }
        });
    }

    $("#directory").on('change', function(e) {
        var cabin = $(this).parents("form").data('currentcabin');
        if ($("option:selected", this).data('cabin') !== cabin) {
            // We're forcing this to be unchecked.
            if ($("#create_redirect").is(":checked")) {
                var chk = '1';
            } else {
                var chk = '0';
            }
            $("#create_redirect_row")
                .data("checked", chk)
                .hide(200);
            $("#create_redirect").prop(
                'checked',
                false
            );
        } else {
            $("#create_redirect").prop(
                'checked',
                $("#create_redirect_row").data("checked") == '1'
            );
            $("#create_redirect_row").show(200);
        }
    });

    $("#cancel_btn").click(function() {
        window.location=$(this).data('href');
    });
});