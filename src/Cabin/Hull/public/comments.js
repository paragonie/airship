window.changedAuthorSelection = function() {
    var authorEl = $("#blog-reply-author");
    if (authorEl) {
        var author = authorEl.val();
        if (author.length < 1) {
            $(".guest-comment-field").show(200);
            $("#blog-comment-name").attr('required', 'required');
            $("#blog-comment-email").attr('required', 'required');
        } else {
            $(".guest-comment-field").hide(200);
            $("#blog-comment-name").removeAttr('required');
            $("#blog-comment-email").removeAttr('required');
        }
    }
};

window.replyTo = function(commentId, author) {
    $("#reply-to").html(
        "<div class='blog-comment-label form-column'></div><div class='form-comment-field form-column'>" +
        "<input type='hidden' name='reply_to' value='" + commentId + "' />" +
            "Replying to " + author + " (Comment #" + commentId + ")" +
        "</div>"
    );
};

window.loadComments = function() {
    $.post("")
};

$(document).ready(function() {
    window.changedAuthorSelection();
    $("#blog-reply-author").on('change', window.changedAuthorSelection);
    $(".reply-link").click(function() {
        window.replyTo(
            $(this).data('replyto'),
            $(this).data('author')
        );
    });
    var comment_wrapper = $("#blog_comments_wrapper");
    if (comment_wrapper.data('cached')) {
        window.loadComments(comment_wrapper.data('cabinurl'));
    }
});