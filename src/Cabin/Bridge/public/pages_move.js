$(document).ready(function() {
    var dir_el = $("#directory");

    if ($.browser.webkit) {
        // Work around webkit being terrible and not letting us style <option> tags:
        dir_el.children("option").each(function () {
            var val = $(this).val();
            if (val !== parseInt(val, 10)) {
                $(this).html("-- " + Airship.e(val) + " --");
            } else {
                $(this).html($(this).data('fullpath'));
            }
        });
    }

    dir_el.on('change', function(e) {
        var cabin = $(this).parents("form").data('currentcabin');
        var chk_el = $("#create_redirect");
        var chk = '0';

        if ($("option:selected", this).data('cabin') !== cabin) {
            // We're forcing this to be unchecked.
            if (chk_el.is(":checked")) {
                chk = '1';
            }
            $("#create_redirect_row")
                .data("checked", chk)
                .hide(200);
            chk_el.prop('checked', false);
        } else {
            var row_el = $("#create_redirect_row");
            chk_el.prop(
                'checked',
                row_el.data("checked") === '1'
            );
            row_el.show(200);
        }
    });

    $("#cancel_btn").click(function() {
        window.location=$(this).data('href');
    });
});
