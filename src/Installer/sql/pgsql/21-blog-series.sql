CREATE TABLE hull_blog_series (
    seriesid BIGSERIAL PRIMARY KEY,
    name TEXT,
    slug TEXT,
    preamble TEXT,
    format TEXT, -- HTML, Markdown, RST (for preamble)
    config JSONB,
    created TIMESTAMP DEFAULT NOW(),
    modified TIMESTAMP DEFAULT NOW(),
    UNIQUE(slug)
);

CREATE TABLE hull_blog_series_items (
    itemid BIGSERIAL PRIMARY KEY,
    listorder INTEGER,
    parent INTEGER NULL,
    post INTEGER NULL,
    series INTEGER NULL,
    CHECK((post IS NULL) != (series IS NULL)),
    created TIMESTAMP DEFAULT NOW(),
    modified TIMESTAMP DEFAULT NOW()
);

DROP TRIGGER IF EXISTS update_hull_blog_series_modtime ON hull_blog_series;
CREATE TRIGGER update_hull_blog_series_modtime
    BEFORE UPDATE ON hull_blog_series
    FOR EACH ROW EXECUTE PROCEDURE update_modtime();

DROP TRIGGER IF EXISTS update_hull_blog_series_items_modtime ON hull_blog_series_items;
CREATE TRIGGER update_hull_blog_series_items_modtime
    BEFORE UPDATE ON hull_blog_series_items
    FOR EACH ROW EXECUTE PROCEDURE update_modtime();