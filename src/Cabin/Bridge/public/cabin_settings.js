var counters = {};

/**
 * Format
 *
 * @param id
 * @param i
 * @param config
 * @returns {string}
 */
window.formatDynamicLink = function(id, i, config) {
    var elem = $("#" + id);
    var dataName = elem.data('name');
    if (typeof config === 'undefined') {
        var config = {};
    }

    return "<li>" +
        "<button type=\"button\" class=\"" + id + "_delete_link\" data-id=\"" + id + "\" title=\"Delete this Link\"> " +
            "<i class=\"fa fa-remove\"></i>" +
        "</button> " +

        "<label>URL:</label> " +
            "<input type=\"text\" name=\"" + dataName + "[" + i + "][url]\" value=\"" +
                ('url' in config
                    ? config['url']
                    : '') +
            "\" /> " +

        "<label>Label:</label> " +
            "<input type=\"text\" name=\"" + dataName + "[" + i + "][label]\" value=\"" + ('label' in config
                ? config['label']
                : ''
            ) + "\" /> " +

        "<input id=\"" + id +"_translate_" + i + "\" type='checkbox' name='" + dataName + "[" + i + "][translate]' value='1' " +
        (config['translate']
                ? "checked='checked'"
                : ""
        ) + " />" +
        "<label for=\"" + id +"_translate_" + i + "\">Translate?</label>" +
    "</li>";
};

window.addDynamicNavLink = function() {
    var id = $(this).data('id');
    counters[id]++;
    $("#" + id + "_filler").append(
        formatDynamicLink(id, counters[id])
    );
};
window.delDynamicNavLink = function() {
    $(this).parent().remove();
};

/**
 * @param id
 * @param config
 */
window.setupDynamicNavigationEditor = function (id, config) {
    var elem = $("#" + id);

    var dataName = elem.data('name');
    var filled = elem.append("<ol id=\"" + id + "_filler\"></ol>")
        .append("<button type=\"button\" id=\"" + id + "_add_link\" data-id=\"" + id + "\" class=\"pure-button pure-button-secondary\">Add Link</button>");
    var fillerHTML = "";
    var n = 0;
    for (var i in config) {
        fillerHTML += window.formatDynamicLink(id, i, config[i]);
        n++;
    }
    counters[id] = n;
    $("#" + id + "_filler").html(fillerHTML);
    $("#" + id + "_add_link").click(window.addDynamicNavLink);
    $("." + id + "_delete_link").click(window.delDynamicNavLink);
};

$(document).ready(function() {
    $("#cabin_accordion").accordion({
        heightStyle: "content"
    });

    $(".dynamic_navigation").each(function() {
        var config = $(this).html();
        $(this).html('');
        return window.setupDynamicNavigationEditor(
            $(this).attr('id'),
            $.parseJSON(config)
        );
    });
});