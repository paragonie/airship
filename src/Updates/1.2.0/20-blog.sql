ALTER TABLE hull_blog_post_versions ADD metadata JSONB;

DROP VIEW view_hull_blog_post;
CREATE VIEW view_hull_blog_post AS
    SELECT DISTINCT ON (p.postid)
            p.postid,
            p.shorturl,
            p.title,
            v.body,
            v.metadata,
            v.published AS latest,
            COALESCE(v.format, p.format) AS format,
            p.status,
            p.cache,
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
                    iv.post, iv.body, iv.published, iv.live, iv.format, iv.metadata
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