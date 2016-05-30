CREATE OR REPLACE VIEW view_hull_users_authors AS
    SELECT
        a.*,
        u.uniqueid,
        j.userid,
        j.in_charge,
        j.default_author_for_user
    FROM
        hull_blog_authors a
    JOIN
        hull_blog_author_owners j
            ON j.authorid = a.authorid
    JOIN
        airship_users u
            ON j.userid = u.userid
    ;

CREATE OR REPLACE VIEW view_hull_blog_post AS
    SELECT DISTINCT ON (p.postid)
            p.postid,
            p.shorturl,
            p.title,
            v.body,
            v.published AS latest,
            COALESCE(v.format, p.format) AS format,
            p.status,
            p.description,
            p.published,
            p.created,
            p.modified,
            a.name AS authorname,
            a.slug AS authorslug,
            p.author,
            c.categoryid,
            c.slug AS categoryslug,
            COALESCE(c.name, 'Uncategorized') AS categoryname,
            date_part('year', p.published) AS blogyear,
            date_part('month', p.published) AS blogmonth,
            p.slug
        FROM
            hull_blog_posts p
        LEFT JOIN
            (
                SELECT
                    iv.post, iv.body, iv.published, iv.live, iv.format
                FROM
                    hull_blog_post_versions iv
                WHERE
                    iv.live
                ORDER BY
                    iv.published DESC
            ) v
                ON v.post = p.postid
        LEFT JOIN
            hull_blog_categories c
                ON p.category = c.categoryid
        LEFT JOIN
            hull_blog_authors a
                ON p.author = a.authorid
        ORDER BY p.postid ASC, v.published DESC
    ;

CREATE OR REPLACE VIEW view_hull_blog_list AS
    SELECT
            p.postid,
            p.shorturl,
            p.title,
            p.format,
            p.status,
            p.description,
            p.published,
            p.created,
            p.modified,
            a.name AS authorname,
            a.slug AS authorslug,
            c.categoryid,
            c.slug AS categoryslug,
            p.author,
            COALESCE(c.name, 'Uncategorized') AS categoryname,
            date_part('year', p.published) AS blogyear,
            date_part('month', p.published) AS blogmonth,
            p.slug
        FROM
            hull_blog_posts p
        LEFT JOIN
            hull_blog_categories c
                ON p.category = c.categoryid
        LEFT JOIN
            hull_blog_authors a
                ON p.author = a.authorid
    ;

CREATE OR REPLACE VIEW view_hull_blog_comments AS
    SELECT
        c.commentid,
        c.author,
        c.blogpost,
        c.replyto,
        c.created,
        c.metadata,
        a.name AS authorname,
        a.slug AS authorslug,
        date_part('year', p.published) AS blogyear,
        date_part('month', p.published) AS blogmonth,
        p.slug
    FROM
        hull_blog_comments c
    LEFT JOIN
        hull_blog_posts p
            ON c.blogpost = p.postid
    LEFT JOIN
        hull_blog_authors a
            ON c.author = a.authorid
    ;