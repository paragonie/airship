$(document).ready(function() {
    $("#pages_list_cabin").on('change', function() {
        var cabin = $(this).val()
            .replace("/", "");
        window.location = $("#bridge_main_menu_left").data('linkprefix') + "/pages/" + cabin;
    });

    $(".dir_rename").on('click', function() {
        var cabin = $("#pages_list_cabin").val()
            .replace("/", "");

        var parent = $("#bridge_page_list_container").data('dir');

        var dir = $(this).parent().parent().attr('data-dir');

        // Let's go to the correct location
        window.location = $("#bridge_main_menu_left").data('linkprefix') +
            "/pages/" + cabin + "/renameDir?dir=" +
            (parent !== "" ? parent + "/" : "") +
            dir;
    });


    $(".dir_delete").on('click', function() {
        var cabin = $("#pages_list_cabin").val()
            .replace("/", "");

        var parent = $("#bridge_page_list_container").data('dir');

        var dir = $(this).parent().parent().attr('data-dir');

        // Let's go to the correct location
        window.location = $("#bridge_main_menu_left").data('linkprefix') +
            "/pages/" + cabin + "/deleteDir?dir=" +
            (parent !== "" ? parent + "/" : "") +
            dir;
    });

    $(".page_edit").on('click', function() {
        var cabin = $("#pages_list_cabin").val()
            .replace("/", "");
        var page = $(this).parent().parent().data('page');
        var dir = $("#bridge_page_list_container").data('dir');

        // Let's go to the correct location
        window.location = $("#bridge_main_menu_left").data('linkprefix') +
            "/pages/" + cabin + "/edit?" +
            (dir !== "" ? "dir=" + dir + "&" : "") +
            "page=" + page;
    });

    $(".page_rename").on('click', function() {
        var cabin = $("#pages_list_cabin").val()
            .replace("/", "");
        var page = $(this).parent().parent().data('page');
        var dir = $("#bridge_page_list_container").data('dir');

        // Let's go to the correct location
        window.location = $("#bridge_main_menu_left").data('linkprefix') +
            "/pages/" + cabin + "/renamePage?" +
            (dir !== "" ? "dir=" + dir + "&" : "") +
            "page=" + page;
    });

    $(".page_history").on('click', function() {
        var cabin = $("#pages_list_cabin").val()
            .replace("/", "");
        var page = $(this).parent().parent().data('page');
        var dir = $("#bridge_page_list_container").data('dir');

        // Let's go to the correct location
        window.location = $("#bridge_main_menu_left").data('linkprefix') +
            "/pages/" + cabin + "/history?" +
            (dir !== "" ? "dir=" + dir + "&" : "") +
            "page=" + page;
    });

    $(".page_delete").on('click', function() {
        var cabin = $("#pages_list_cabin").val()
            .replace("/", "");
        var page = $(this).parent().parent().data('page');
        var dir = $("#bridge_page_list_container").data('dir');

        // Let's go to the correct location
        window.location = $("#bridge_main_menu_left").data('linkprefix') +
            "/pages/" + cabin + "/deletePage?" +
            (dir !== "" ? "dir=" + dir + "&" : "") +
            "page=" + page;
    });

    $("#new_dir").on('click', function() {
        var cabin = $("#pages_list_cabin").val()
            .replace("/", "");
        var dir = $("#bridge_page_list_container").data('dir');

        // Let's go to the correct location
        window.location = $("#bridge_main_menu_left").data('linkprefix') +
            "/pages/" + cabin + "/newDir?dir=" + dir;
    });

    $("#new_page").on('click', function() {
        var cabin = $("#pages_list_cabin").val()
            .replace("/", "");
        var dir = $("#bridge_page_list_container").data('dir');

        // Let's go to the correct location
        window.location = $("#bridge_main_menu_left").data('linkprefix') +
            "/pages/" + cabin + "/newPage?dir=" + dir;
    })

});