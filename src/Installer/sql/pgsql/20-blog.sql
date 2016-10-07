CREATE TABLE IF NOT EXISTS hull_blog_categories (
    categoryid BIGSERIAL PRIMARY KEY,
    parent INTEGER NULL,
    name TEXT,
    preamble TEXT,
    slug TEXT,
    created TIMESTAMP DEFAULT NOW(),
    modified TIMESTAMP DEFAULT NOW(),
    UNIQUE(slug)
);

CREATE TABLE IF NOT EXISTS hull_blog_tags (
    tagid serial PRIMARY KEY,
    name TEXT,
    slug TEXT,
    created TIMESTAMP DEFAULT NOW(),
    modified TIMESTAMP DEFAULT NOW(),
    UNIQUE(slug)
);

CREATE TABLE IF NOT EXISTS hull_blog_posts (
    postid BIGSERIAL PRIMARY KEY,
    title TEXT,
    slug TEXT,
    shorturl TEXT,
    description TEXT,
    format TEXT,
    author integer,
    category integer NULL,
    created TIMESTAMP DEFAULT NOW(),
    modified TIMESTAMP DEFAULT NOW(),
    published TIMESTAMP DEFAULT NOW(),
    status boolean DEFAULT FALSE,
    cache boolean DEFAULT FALSE,
    UNIQUE(slug)
);

CREATE TABLE IF NOT EXISTS hull_blog_post_versions (
    versionid BIGSERIAL PRIMARY KEY,
    post BIGINT,
    body TEXT,
    uniqueid TEXT,
    format TEXT,
    metadata JSONB,
    live BOOLEAN DEFAULT FALSE,
    published_by INTEGER,
    modified TIMESTAMP DEFAULT NOW(),
    published TIMESTAMP DEFAULT NOW()
);
CREATE UNIQUE INDEX ON hull_blog_post_versions(uniqueid);

CREATE TABLE IF NOT EXISTS hull_blog_post_tags (
    postid integer,
    tagid integer,
    created TIMESTAMP DEFAULT NOW(),
    modified TIMESTAMP DEFAULT NOW()
);

DROP TRIGGER IF EXISTS update_hull_blog_categories_modtime ON hull_blog_categories;
CREATE TRIGGER update_hull_blog_categories_modtime
    BEFORE UPDATE ON hull_blog_categories
    FOR EACH ROW EXECUTE PROCEDURE update_modtime();

DROP TRIGGER IF EXISTS update_hull_blog_posts_modtime ON blog_posts;
CREATE TRIGGER update_hull_blog_posts_modtime
    BEFORE UPDATE ON hull_blog_posts
    FOR EACH ROW EXECUTE PROCEDURE update_modtime();

DROP TRIGGER IF EXISTS update_hull_blog_post_versions_modtime ON hull_blog_post_versions;
CREATE TRIGGER update_hull_blog_post_versions_modtime
    BEFORE UPDATE ON hull_blog_post_versions
    FOR EACH ROW EXECUTE PROCEDURE update_modtime();

DROP TRIGGER IF EXISTS update_hull_blog_tags_modtime ON blog_tags;
CREATE TRIGGER update_hull_blog_tags_modtime
    BEFORE UPDATE ON hull_blog_tags
    FOR EACH ROW EXECUTE PROCEDURE update_modtime();

DROP TRIGGER IF EXISTS update_hull_blog_post_tags_modtime ON blog_post_tags;
CREATE TRIGGER update_hull_blog_post_tags_modtime
    BEFORE UPDATE ON hull_blog_post_tags
    FOR EACH ROW EXECUTE PROCEDURE update_modtime();