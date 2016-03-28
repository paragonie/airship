$(document).ready(function() {
    $("#parent").children("option").each(function () {
        $(this).html($(this).data('fullpath'));
    });
});