ALTER TABLE airship_users ADD gpg_public_key TEXT;
ALTER TABLE airship_users ADD allow_reset BOOLEAN DEFAULT FALSE;
ALTER TABLE airship_users ADD totp_secret TEXT;
ALTER TABLE airship_users ADD enable_2factor BOOLEAN DEFAULT FALSE;
ALTER TABLE airship_users ADD session_canary TEXT;
ALTER TABLE hull_blog_posts ADD cache boolean DEFAULT FALSE;

DROP VIEW view_hull_blog_post;
CREATE VIEW view_hull_blog_post AS
    SELECT DISTINCT ON (postid)
            p.postid,
            p.shorturl,
            p.title,
            v.body,
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

CREATE TABLE airship_user_recovery (
    tokenid BIGSERIAL PRIMARY KEY,
    userid BIGINT REFERENCES airship_users( userid),
    selector TEXT,
    hashedtoken TEXT,
    created TIMESTAMP DEFAULT NOW(),
    modified TIMESTAMP DEFAULT NOW()
);
CREATE INDEX ON airship_user_recovery (selector);

DROP TRIGGER IF EXISTS update_airship_user_recovery_modtime ON airship_user_recovery;
CREATE TRIGGER update_airship_user_recovery_modtime
  BEFORE UPDATE ON airship_user_recovery
  FOR EACH ROW EXECUTE PROCEDURE update_modtime();

CREATE TABLE airship_failed_logins (
    failureid BIGSERIAL PRIMARY KEY,
    username TEXT,
    ipaddress TEXT,
    sealed_password TEXT,
    occurred TIMESTAMP DEFAULT NOW()
);

CREATE INDEX ON airship_failed_logins (username);
CREATE INDEX ON airship_failed_logins (ipaddress);
CREATE INDEX ON airship_failed_logins (username, ipaddress);
