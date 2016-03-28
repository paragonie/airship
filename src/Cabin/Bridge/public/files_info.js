$(document).ready(function() {

    $("#bridge_files_info_img_nav_html").addClass("active");
    $("#bridge_files_info_img_markdown").hide();
    $("#bridge_files_info_img_rst").hide();

    $("#bridge_files_info_img_nav > li").on('click', function() {
        var format = $(this).data('format');
        $("#bridge_files_info_img_nav > li")
            .removeClass('active');

        $("#bridge_files_info_img_nav_" + format.toLowerCase())
            .addClass('active');

        $("#bridge_files_info_img_code div").hide();
        $("#bridge_files_info_img_" + format.toLowerCase()).show();
    });
});