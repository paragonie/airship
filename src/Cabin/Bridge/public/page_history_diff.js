$(document).ready(function () {
    var left = $("#diff_src_left").val();
    var right = $("#diff_src_right").val();
    
    var diff = JsDiff.diffLines(left, right, {
        "ignoreWhitespace": true
    });
    var output = '';
    var pieces = [];

    diff.forEach(function(change) {
        pieces = change.value.split("\n");
        for (var i in pieces) {
            if (change.added) {
                pieces[i] = '+ ' + Airship.e(pieces[i]);
            } else if (change.removed) {
                pieces[i] = '- ' + Airship.e(pieces[i]);
            }
        }
        output += "<div class=\"diff_line" +
                (change.added ? ' diff_add' : '') +
                (change.removed ? ' diff_del' : '') +
            "\">" +
                pieces.join("<br />") +
            "</div>";
    });
    $("#diff_output").html(output);
});