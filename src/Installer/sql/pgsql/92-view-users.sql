CREATE OR REPLACE VIEW view_bridge_user_directory AS
    SELECT
        u.uniqueid AS public_id,
        COALESCE(u.display_name, u.real_name, '') AS display_name,
        up.directoryinfo
    FROM
        airship_users u
    LEFT JOIN
        airship_user_preferences up
        ON up.userid = u.userid
    WHERE
        up.publicprofile
    ;