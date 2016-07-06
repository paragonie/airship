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

    "addEvents": function() {
        $("#add-db-group").on('click', function() {
            var next = $(this).data('next');
            database.addGroup(next);
            $(this).data('next', next + 1);
        });

        $(".database-add-connection").on('click', function() {
            var next = $(this).data('next');
            database.addConnection($(this).data('counter'), next);
            $(this).data('next', next + 1);
        });
    },

    "addGroup": function(index) {
        var rendered = database.groupTemplate
            .split('{counter}').join(index);

        $("#database-form").append(rendered);

        database.addConnection(index, 0);

        database.addEvents();
    },

    "groupTemplate": "",

    "template": ""
};

$(document).ready(function() {
    database["template"] = $("#database-template").val();

    database["groupTemplate"] = "<fieldset id=\"db-{counter}\">" + "\n" +
    "        <legend>" + "\n" +
    "        <input" + "\n" +
    "            type=\"text\"" + "\n" +
        "            name=\"db_keys[{counter}]\"" + "\n" +
        "            placeholder=\"" + $("#database-group-placeholder").val() + "\"" + "\n" +
    "            required=\"required\"" + "\n" +
    "        />" + "\n" +
    "        </legend>" + "\n" +
    "\n" +
    "        <ol class=\"database-inline\" id=\"db-{counter}-connections\">" + "\n" +
    "        </ol>" + "\n" +
    "       <button" + "\n" +
    "           class=\"pure-button pure-button-secondary database-add-connection\"" + "\n" +
    "           type=\"button\"" + "\n" +
    "           id=\"db-{counter}-add-connection\"" + "\n" +
    "           data-counter=\"{counter}\"" + "\n" +
    "           data-next=\"1\"" + "\n" +
    "   >" + "\n" +
    "        " + $("#database-add-connection-text").val() + "\n" +
    "       </button>" + "\n" +
    "</fieldset>";

    database.addEvents();
});