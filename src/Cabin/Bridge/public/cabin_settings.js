var counters = {};

/**
 * Format
 *
 * @param id
 * @param i
 * @param config
 * @param idPrefix
 * @returns {string}
 */
window.formatDynamicLink = function(id, i, config, idPrefix) {
    var elem = $("#" + id);
    var dataName = elem.data('name');
    if (typeof config === 'undefined') {
        var config = {};
    }

    return "<li>" + "\n" +
        "<button type=\"button\" id=\"" + idPrefix + "_delete_link_" + i + "\" class=\"" + idPrefix + "_delete_link\" data-id=\"" + id + "\" title=\"Delete this Link\"> " +
        "<i class=\"fa fa-remove\"></i>" +
        "</button> " + "\n" +

        "<label>URL:</label> " +
        "<input type=\"text\" name=\"" + dataName + "[" + i + "][url]\" value=\"" +
        ('url' in config
            ? config['url']
            : '') +
        "\" /> " + "\n" +

        "<label>Label:</label> " +
        "<input type=\"text\" name=\"" + dataName + "[" + i + "][label]\" value=\"" + ('label' in config
                ? config['label']
                : ''
        ) + "\" /> " + "\n" +
        "<input id=\"" + idPrefix +"_translate_" + i + "\" type='checkbox' name='" + dataName + "[" + i + "][translate]' value='1'" +
        (config.translate
                ? " checked='checked'"
                : ""
        ) + " />" +
        "<label for=\"" + idPrefix +"_translate_" + i + "\">Translate?</label>" +
        "</li>" + "\n";
};

window.addDynamicNavLink = function() {
    var id = $(this).data('id');
    var idPrefix = $(this).data('idprefix');
    counters[id]++;
    var formatted = formatDynamicLink(id, counters[id], {}, idPrefix);
    console.log(formatted);
    $("#" + idPrefix + "_filler").append(formatted);
    $("#" + idPrefix + "_delete_link_" + counters[id]).click(window.delDynamicNavLink);
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
    var idPrefix = elem.data('idprefix');
    var filled = elem.append("<ol id=\"" + idPrefix + "_filler\"></ol>")
        .append(
            "<button " +
            "type=\"button\" " +
            "data-idprefix=\"" + idPrefix + "\" " +
            "id=\"" + idPrefix + "_add_link\" " +
            "data-id=\"" + id + "\" " +
            "class=\"pure-button pure-button-secondary\"" +
            ">Add Link</button>" + "\n"
        );
    var fillerHTML = "";
    var n = 0;
    for (var i in config) {
        fillerHTML += window.formatDynamicLink(id, i, config[i], idPrefix);
        n++;
    }
    counters[id] = n;
    $("#" + idPrefix + "_filler").html(fillerHTML);
    $("#" + idPrefix + "_add_link").click(window.addDynamicNavLink);
    $("." + idPrefix + "_delete_link").click(window.delDynamicNavLink);
};

window.hpkpAdd = function () {
    var addHash = $("#add-hpkp-hash");
    var num = parseInt(addHash.data('numhashes'), 10);
    $("#hpkp-hashes").append(
        "<li><input class=\"full-width\" type=\"text\" name=\"config[hpkp][hashes][" + num + "][hash]\"/></li>"
    );
    addHash.data('numhashes', (num + 1));
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
    $("#add-hpkp-hash").on('click', function() {
        return window.hpkpAdd();
    });

    $("#clear-cache-button").on('click', function() {
        var base_url = $("#clear_cache").data('baseurl');
        var val = $("#clear_cache").data('saved');
        $.get(
            base_url + 'ajax/clear_cache/' + val,
            function (result) {
                if (result.status !== "OK") {
                    alert(result.message);
                }
            }
        );
    });
});