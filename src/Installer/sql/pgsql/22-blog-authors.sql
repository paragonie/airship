CREATE TABLE hull_blog_authors (
    authorid BIGSERIAL PRIMARY KEY,
    name TEXT,
    bio_format TEXT,
    biography TEXT,
    byline TEXT,
    slug TEXT,
    created TIMESTAMP DEFAULT NOW(),
    modified TIMESTAMP DEFAULT NOW()
);
CREATE UNIQUE INDEX ON hull_blog_authors (slug);

CREATE TABLE hull_blog_author_owners (
    userid BIGINT,
    authorid BIGINT,
    default_author_for_user BOOLEAN DEFAULT FALSE,
    in_charge BOOLEAN DEFAULT FALSE,
    created TIMESTAMP DEFAULT NOW()
);
CREATE INDEX ON hull_blog_author_owners (userid);
CREATE INDEX ON hull_blog_author_owners (authorid);
CREATE UNIQUE INDEX ON hull_blog_author_owners (userid, authorid);

ALTER TABLE airship_files ADD
    author BIGINT NULL REFERENCES hull_blog_authors (authorid);

ALTER TABLE hull_blog_series ADD
    author BIGINT NULL REFERENCES hull_blog_authors (authorid);

CREATE INDEX ON hull_blog_authors (authorid);

CREATE TABLE hull_blog_photo_contexts (
    contextid BIGSERIAL PRIMARY KEY,
    label TEXT,
    display_name TEXT
);
CREATE UNIQUE INDEX ON hull_blog_photo_contexts (label);

CREATE TABLE hull_blog_author_photos (
    photoid BIGSERIAL PRIMARY KEY,
    author BIGINT REFERENCES hull_blog_authors (authorid),
    file BIGINT REFERENCES airship_files (fileid),
    context BIGINT REFERENCES hull_blog_photo_contexts (contextid),
    created TIMESTAMP DEFAULT NOW(),
    modified TIMESTAMP DEFAULT NOW()
);

CREATE UNIQUE INDEX ON hull_blog_author_photos (author, context);