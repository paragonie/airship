/**
 * Add a blog post to the list, exclude it (and its children) from being displayed
 *
 * @param new_item
 */
window.add_blog_post = function (new_item) {
    $("#blog_series_items_sortable").append(new_item);
    update_series_items();
};

/**
 * Add a series to the list, exclude it (and its children) from being displayed
 *
 * @param new_item
 */
window.add_blog_series = function (new_item) {
    $("#blog_series_items_sortable").append(new_item);
    update_series_items();
};

/**
 * Serialize
 */
window.update_series_items = function() {
    var ser = $("#blog_series_items_sortable")
        .sortable("toArray", {
            "attribute": "data-id"
        });
    $("#blog_series_items_serialized").val(ser);

    var authorId = $("#author").val();
    if (authorId > 0) {
        populate_series_select(authorId);
        populate_blogposts_select(authorId);
    }
};

/**
 * Get all the items for a given type
 * @param type
 */
window.get_items = function(type) {
    var res = [];
    $("#blog_series_items_sortable li").each(function (i, el) {
        if ($(el).data('type') == type) {
            res.push(
                $(el).data('rowid')
            );
        }
    });
    return res;
};

window.populate_blogposts_select = function(authorId) {
    window.existing_blogposts = get_items('blogpost');
    var prefix = $("#bridge_main_menu_left").data('linkprefix');
    $.post(
        prefix + "ajax/authors_blog_posts",
        {
            "author": authorId,
            "existing": window.existing_blogposts
        },
        function (res) {
            if (res.status === 'OK') {
                $("#existing_posts").html(res.options);
            }
        }
    );
};

/**
 * Update the <select> dropdown for #existing_series
 *
 * @param authorId
 */
window.populate_series_select = function(authorId) {
    window.existing_series = get_items('series');
    if ($("#series_id").length) {
        window.existing_series.push(
            $("#series_id").val()
        );
    }
    var prefix = $("#bridge_main_menu_left").data('linkprefix');
    $.post(
        prefix + "ajax/authors_blog_series",
        {
            "author": authorId,
            "existing": window.existing_series
        },
        function (res) {
            if (res.status === 'OK') {
                $("#existing_series").html(res.options);
            }
        }
    );
};

window.populate_selects_for_author = function() {
    var authorId = $("#author").val();
    if (authorId > 0) {
        populate_series_select(authorId);
        populate_blogposts_select(authorId);
    }
};

/**
 * Bind the onClick event to delete a series or blog post from the list
 *
 * @param sel
 */
window.add_delete_event = function(sel) {
    $(sel).click(function () {
        var id = $(this).data('id');
        $("#series_items_" + id).remove();
        update_series_items();
    });
};

$(document).ready(function() {
    $("#blog_series_items_sortable")
        .sortable({
            "update": update_series_items
        });

    $("#author").change(function() {
        populate_selects_for_author();
    });
    window.existing_series = get_items('series');
    window.existing_blogposts = get_items('blogpost');
    add_delete_event(".delete_item");

    $("#add_series_btn").click(function() {
        var authorId = $("#author").val();
        if (authorId > 0) {
            var addingId = $("#existing_series").val();
            if (addingId > 0) {
                var prefix = $("#bridge_main_menu_left").data('linkprefix');
                $.post(
                    prefix + "ajax/authors_blog_series",
                    {
                        "add": addingId,
                        "author": authorId,
                        "existing": window.existing_series
                    },
                    function (res) {
                        if (res.status == 'OK') {
                            add_blog_series(res["new_item"]);
                            $("#existing_series").html(res.options);
                            add_delete_event("#series_items_series_" + addingId + " .delete_item");
                        }
                    }
                );
            } else {
                alert("Please select a series");
            }
        } else {
            alert("Please select an author");
        }
    });

    $("#add_blogpost_btn").click(function() {
        var authorId = $("#author").val();
        if (authorId > 0) {
            var addingId = $("#existing_posts").val();
            if (addingId > 0) {
                var prefix = $("#bridge_main_menu_left").data('linkprefix');
                $.post(
                    prefix + "ajax/authors_blog_posts",
                    {
                        "add": addingId,
                        "author": authorId,
                        "existing": window.existing_blogposts
                    },
                    function (res) {
                        if (res.status == 'OK') {
                            add_blog_post(res["new_item"]);
                            $("#existing_posts").html(res.options);
                            add_delete_event("#series_items_blogpost_" + addingId + " .delete_item");
                        }
                    }
                );
            } else {
                alert("Please select a series");
            }
        } else {
            alert("Please select an author");
        }
    });

    populate_selects_for_author();
    add_delete_event(".delete_item");
});