CREATE TABLE IF NOT EXISTS hull_blog_comments (
  commentid BIGSERIAL PRIMARY KEY,
  blogpost BIGINT REFERENCES hull_blog_posts(postid),
  replyto BIGINT NULL,
  author BIGINT NULL REFERENCES hull_blog_authors(authorid),
  metadata JSONB,
  approved BOOLEAN DEFAULT FALSE,
  created TIMESTAMP DEFAULT NOW(),
  modified TIMESTAMP DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS ON hull_blog_comments (replyto);

CREATE TABLE IF NOT EXISTS hull_blog_comment_versions (
  versionid BIGSERIAL PRIMARY KEY,
  comment BIGINT REFERENCES hull_blog_comments(commentid),
  approved BOOLEAN DEFAULT FALSE,
  message TEXT,
  created TIMESTAMP DEFAULT NOW(),
  modified TIMESTAMP DEFAULT NOW()
);

DROP TRIGGER IF EXISTS update_hull_blog_comments_modtime ON hull_blog_comments;
CREATE TRIGGER update_hull_blog_comments_modtime
BEFORE UPDATE ON hull_blog_comments
FOR EACH ROW EXECUTE PROCEDURE update_modtime();

DROP TRIGGER IF EXISTS update_hull_blog_comment_vesrions_modtime ON hull_blog_comment_versions;
CREATE TRIGGER update_hull_blog_comment_versions_modtime
BEFORE UPDATE ON hull_blog_comment_versions
FOR EACH ROW EXECUTE PROCEDURE update_modtime();