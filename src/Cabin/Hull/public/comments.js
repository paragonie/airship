window.changedAuthorSelection = function() {
    var author = $("#blog-reply-author").val();
    if (author.length < 1) {
        $(".guest-comment-field").show(200);
    } else {
        $(".guest-comment-field").hide(200);
    }
};

$(document).ready(function() {
    window.changedAuthorSelection();
    $("#blog-reply-author").on('change', window.changedAuthorSelection);
});