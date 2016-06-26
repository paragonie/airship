$(document).ready(function() {
    $(".announce-dismiss").on('click', function() {
        var id = $(this).data('id');
        console.log($("#bridge_main_menu_left").data('linkprefix') + "announcement/dismiss");
        $.post(
            $("#bridge_main_menu_left").data('linkprefix') + "announcement/dismiss",
            {
                "dismiss": id
            },
            function (result) {
                if (result.status == 'OK') {
                    $("#announce-" + id).remove();
                    var container = $("#announcements");
                    if (container.html().trim() === '') {
                        container.html(
                            "<em>" + container.data('noentries') + "</em>"
                        );
                    }
                }
            }
        )
    });
});
