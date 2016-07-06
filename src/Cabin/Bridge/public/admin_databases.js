var database = {

    "addConnection": function(key, index) {
        var rendered = database.template
                .split('{counter}').join(key)
                .split('&#x7B;counter&#x7D;').join(key)
                .split('&amp;#x7B;counter&amp;#x7D;').join(key)
                .split('{id}').join(index)
                .split('&#x7B;id&#x7D;').join(index)
                .split('&amp;#x7B;id&amp;#x7D;').join(index);

        $("#db-" + key + "-connections")
            .append("<li>" + rendered + "</li>");
    },

    "template": ""
};

$(document).ready(function() {
    database["template"] = $("#database-template").val();

    $(".database-add-connection").on('click', function() {
        var next = $(this).data('next');
        database.addConnection($(this).data('counter'), next);
        $(this).data('next', next + 1);
    });
});