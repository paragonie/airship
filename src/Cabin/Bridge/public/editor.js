/**
 * Basic editor with context switch
 */
var editors = {};

window.richTextLookups = {};
window.editor_is_wysiwyg = false;
window.editor_html_wrapper = {};

$(document).ready(function() {
    window.richTextUpdate = function(_name, show_after) {
        var prefix = $("#bridge_main_menu_left").data("linkprefix");
        _name = Airship.filter(_name, Airship.FILTER_ID);
        $.post(
            prefix + "ajax/rich_text_preview",
            {
                "format": $("#" + richTextLookups[_name]).val(),
                "body": $("#rich_text_" + _name).val(),
                "csrf_token": $("body").data('ajaxtoken')
            },
            function (response) {
                if (typeof(response) === "string") {
                    response = $.parseJSON(response);
                }
                if (response.status === "OK") {
                    var preview_el = $("#rich_text_" + _name + "_preview");
                    preview_el.html(response.body);
                    if (show_after) {
                        $("#rich_text_" + _name + "_text").hide();
                        preview_el.show();
                    }
                } else {
                    alert(status.message);
                }
            }
        );
    };

    window['richTextWrapper'] = function(name, format_name) {
        name = Airship.filter(name, Airship.FILTER_ID);
        richTextLookups[name] = format_name;

        var editTab = $("#rich_text_" + name +"_edit_tab");
        editTab.addClass("active_tab");

        editTab.click(function(e) {
            var _name = $(this).parent().parent().data('name');
            $(this).parent().each(function () {
                $("a").removeClass("active_tab");
            });
            $(this).addClass("active_tab");
            $("#rich_text_" + _name + "_text").show();
            $("#rich_text_" + _name + "_preview").hide();
        });

        $("#rich_text_" + name +"_preview_tab").click(function(e) {
            var _name = $(this).parent().parent().data('name');
            $(this).parent().each(function () {
                $("a").removeClass("active_tab");
            });
            $(this).addClass("active_tab");
            richTextUpdate(_name, true);
        });

        var format_name_el = $("#"+format_name);
        format_name_el.on('change', function(e) {
            for (k in richTextLookups) {
                var rich_text_el = $("#rich_text_" + k);
                if ($(this).attr('id') === richTextLookups[k]) {
                    if ($(this).val() === "Rich Text") {
                        window.useWysiwyg(k);
                    } else if (window.editor_is_wysiwyg) {
                        var cache_body = rich_text_el.val();
                        delete editors[k];
                        $("#rich_text_" + k + "_wrapper").html(
                            window.editor_html_wrapper[k]
                        );
                        rich_text_el.val(cache_body);
                        $("#rich_text_" + k + "_tabs").show();
                    }
                    if ($("#rich_text_" + k + "_preview").is(':visible')) {
                        return richTextUpdate(k, false);
                    }
                    return;
                }
            }
        });

        $("#rich_text_" + name + "_text").show();
        $("#rich_text_" + name + "_preview").hide();

        if (format_name_el.val() === "Rich Text") {
            useWysiwyg(name);
        }

    };

    window.useWysiwyg = function (k) {
        window.editor_is_wysiwyg = true;
        window.editor_html_wrapper[k] = $("#rich_text_" + k + "_wrapper").html();

        editors[k] = new wysihtml5.Editor(
            "rich_text_" + k,
            {
                toolbar:        "rich_text_" + k + "_toolbar",
                parserRules:    wysihtml5ParserRules,
                useLineBreaks:  false,
                showToolbarAfterInit: true
            }
        );
        $("#rich_text_" + k + "_tabs").hide();
    };

});
