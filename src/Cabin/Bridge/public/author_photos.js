window.photoSelectorState = {
    'photoDir': 'photos',
    'authorID': '',
    'authorSlug': '',
    'cabin': '',
    'cabin_url': '',
    'context': '',
    'selectedPhoto': '',
    'selectedCache': {}
};

window.photoSelector = function() {
    /**
     * Get the available photos for a given cabin and author
     */
    this.getAvailablePhotosList = function() {
        $.post(
            $("#bridge_main_menu_left").data('linkprefix') +
                "ajax/authors_photo_available",
            {
                "cabin": window.photoSelectorState.cabin,
                "author": window.photoSelectorState.authorID,
                "csrf_token": $("body").data('ajaxtoken')
            },
            function (res) {
                if (res.status !== "OK") {
                    return;
                }
                $("#change-photo-btn").removeAttr('disabled');
                var html = '<option value=""> -- None --</option>' + "\n";
                window.photoSelector.selectedCache = {};
                var dupes = [];
                for (var i in res.photos) {
                    // Selected
                    if (res.photos[i].context === window.photoSelectorState.context) {
                        window.photoSelectorState.selectedCache[res.photos[i].context] = res.photos[i];
                        window.photoSelectorState.selectedPhoto = res.photos[i].filename;
                        if (dupes.indexOf(res.photos[i].filename) < 0) {
                            html += "<option value=\"" +
                                    Airship.e(res.photos[i].filename) +
                                "\" selected=\"selected\" data-context=\"" +
                                    Airship.e(res.photos[i].context) +
                                "\">" +
                                    Airship.e(res.photos[i].filename, Airship.E_HTML) +
                                "</option>\n";
                        }

                    // Selected for another context:
                    } else if (res.photos[i].context !== null) {
                        window.photoSelectorState.selectedCache[res.photos[i].context] = res.photos[i].filename;
                        if (dupes.indexOf(res.photos[i].filename) < 0) {
                            html += "<option value=\"" +
                                    Airship.e(res.photos[i].filename) +
                                "\" data-context=\"" +
                                    Airship.e(res.photos[i].context) +
                                "\">" +
                                    Airship.e(res.photos[i].filename, Airship.E_HTML) +
                                "</option>\n";
                        }
                    // Not selected:
                    } else {
                        if (dupes.indexOf(res.photos[i].filename) < 0) {
                            html += "<option value=\"" +
                                Airship.e(res.photos[i].filename) +
                            "\">" +
                                Airship.e(res.photos[i].filename, Airship.E_HTML) +
                            "</option>\n";
                        }
                    }
                    dupes.push(res.photos[i].filename);
                }
                $("#selected-filename").html(html);
            }
        );
    };

    this.selectedCabin = function(obj) {
        $(".photo-selector-cabin").removeClass('active');
        obj.addClass('active');
        window.photoSelectorState.cabin = obj.data('cabin');
        window.photoSelectorState.cabin_url = obj.data('url');
        console.log(window.photoSelectorState.cabin_url);
        if (window.photoSelectorState.context !== "") {
            this.updateOriginalPhoto();
            this.updateSelectedPhoto();
        }
        $("#directory-source").attr(
            'href',
            $("#bridge_main_menu_left").data('linkprefix') +
                "author/files/" +
                window.photoSelectorState.authorID + "/" +
                window.photoSelectorState.cabin +
                "?dir=" + window.photoSelectorState.photoDir
        );
        this.getAvailablePhotosList();
    };

    this.selectedContext = function(obj) {
        $(".photo-selector-context").removeClass('active');
        obj.addClass('active');
        window.photoSelectorState.context = obj.data('context');
        this.updateOriginalPhoto();
        if (window.photoSelectorState.cabin !== "") {
            this.updateSelectedPhoto();
            this.getAvailablePhotosList();
        }
    };

    /**
     * Update the image preview:
     */
    this.updateOriginalPhoto = function() {
        $.post(
            $("#bridge_main_menu_left").data('linkprefix') + "ajax/authors_get_photo",
            {
                'context': window.photoSelectorState.context,
                "author": window.photoSelectorState.authorID,
                "csrf_token": $("body").data('ajaxtoken')
            },
            function (res) {
                if (res.status !== "OK") {
                    return;
                }
                console.log(res);
                if (res.photo === null) {
                    $("#photo-selector-current").html("");
                } else {
                    $("#photo-selector-current").html(
                        "<img src=\"" + Airship.e(res.photo) + "\" />"
                    );
                }
            }
        );
    };

    this.updateSelectedPhoto = function() {
        console.log(window.photoSelectorState.selectedPhoto);
        if (window.photoSelectorState.selectedPhoto === "") {
            $("#photo-selector-selected").html("");
        } else {
            $("#photo-selector-selected").html(
                "<img src=\"" +
                    window.photoSelectorState.cabin_url +
                    "files/author/" + window.photoSelectorState.authorSlug + "/" +
                    Airship.e(
                        window.photoSelectorState.photoDir + "/" +
                        window.photoSelectorState.selectedPhoto,
                        Airship.E_URL
                    ) +
                "\" />"
            );
        }
    };

    /**
     * Update the image preview:
     */
    this.changeSelectedPhoto = function(filename) {
        window.photoSelectorState.selectedPhoto = filename;
        this.updateSelectedPhoto();
    };

    /**
     * Update the image preview:
     */
    this.savePhotoChoice = function(obj) {
        console.log({
            "author": window.photoSelectorState.authorID,
            "cabin": window.photoSelectorState.cabin,
            "context": window.photoSelectorState.context,
            "filename": window.photoSelectorState.selectedPhoto
        });
        $.post(
            $("#bridge_main_menu_left").data('linkprefix') + "ajax/authors_save_photo",
            {
                "author": window.photoSelectorState.authorID,
                "cabin": window.photoSelectorState.cabin,
                "context": window.photoSelectorState.context,
                "filename": window.photoSelectorState.selectedPhoto,
                "csrf_token": $("body").data('ajaxtoken')
            },
            function (res) {
                if (res.status === "OK") {
                    window.updateOriginalPhoto();
                }
            }
        );
    };

    $(".photo-selector-context").click(function () {
        return window.selectedContext($(this));
    });
    $(".photo-selector-cabin").click(function() {
        return window.selectedCabin($(this));
    });
    $("#photo-select-refresh").click(function() {
        return window.getAvailablePhotosList();
    });
    $("#selected-filename").on('change', function() {
        return window.changeSelectedPhoto($(this).val());
    });
    $("#change-photo-btn").click(function() {
        return window.savePhotoChoice();
    });
};

$(document).ready(function() {
    $("#change-photo-btn").attr('disabled', 'disabled');
    window.photoSelector();
    window.photoSelectorState.authorID = $("#photo-selector")
        .data('authorid');
    window.photoSelectorState.authorSlug = $("#photo-selector")
        .data('authorslug');
});