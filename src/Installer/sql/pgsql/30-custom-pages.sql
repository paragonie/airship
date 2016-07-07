CREATE TABLE IF NOT EXISTS airship_custom_dir (
  directoryid BIGSERIAL PRIMARY KEY,
  active BOOLEAN DEFAULT FALSE,
  cabin TEXT,
  parent BIGINT NULL,
  url TEXT,
  created TIMESTAMP DEFAULT NOW(),
  modified TIMESTAMP DEFAULT NOW(),
  UNIQUE(cabin, parent, url)
);
CREATE INDEX ON airship_custom_dir (cabin);
CREATE INDEX ON airship_custom_dir (parent);
CREATE INDEX ON airship_custom_dir (url);

CREATE TABLE IF NOT EXISTS airship_custom_page (
  pageid BIGSERIAL PRIMARY KEY,
  active BOOLEAN DEFAULT FALSE,
  cache BOOLEAN DEFAULT FALSE,
  cabin TEXT,
  directory BIGINT NULL,
  url TEXT,
  created TIMESTAMP DEFAULT NOW(),
  modified TIMESTAMP DEFAULT NOW()
);
CREATE INDEX ON airship_custom_page (cabin);
CREATE INDEX ON airship_custom_page (directory);
CREATE INDEX ON airship_custom_page (url);

CREATE TABLE IF NOT EXISTS airship_custom_page_version (
  versionid BIGSERIAL PRIMARY KEY,
  page BIGINT,
  uniqueid TEXT,
  published BOOLEAN DEFAULT FALSE,
  formatting TEXT,
  bridge_user BIGINT,
  body TEXT,
  metadata JSONB,
  raw BOOLEAN DEFAULT FALSE,
  created TIMESTAMP DEFAULT NOW(),
  modified TIMESTAMP DEFAULT NOW(),
  FOREIGN KEY(page) REFERENCES airship_custom_page (pageid)
);
CREATE INDEX ON airship_custom_page_version (published);
CREATE UNIQUE INDEX ON airship_custom_page_version (uniqueid);

CREATE TABLE IF NOT EXISTS airship_custom_redirect (
  redirectid BIGSERIAL PRIMARY KEY,
  cabin TEXT,
  oldpath TEXT,
  newpath TEXT,
  same_cabin BOOLEAN DEFAULT FALSE,
  created TIMESTAMP DEFAULT NOW(),
  modified TIMESTAMP DEFAULT NOW()
);
CREATE INDEX ON airship_custom_redirect(cabin);
CREATE INDEX ON airship_custom_redirect(oldpath);
CREATE UNIQUE INDEX ON airship_custom_redirect(cabin, oldpath, newpath);

DROP TRIGGER IF EXISTS update_airship_custom_dir_modtime ON airship_custom_dir;
CREATE TRIGGER update_airship_custom_dir_modtime
  BEFORE UPDATE ON airship_custom_dir
  FOR EACH ROW EXECUTE PROCEDURE update_modtime();

DROP TRIGGER IF EXISTS update_airship_custom_page_modtime ON airship_custom_page;
CREATE TRIGGER update_airship_custom_page_modtime
  BEFORE UPDATE ON airship_custom_page
  FOR EACH ROW EXECUTE PROCEDURE update_modtime();

DROP TRIGGER IF EXISTS update_airship_custom_page_version_modtime ON airship_custom_page_version;
CREATE TRIGGER update_airship_custom_page_version_modtime
  BEFORE UPDATE ON airship_custom_page_version
  FOR EACH ROW EXECUTE PROCEDURE update_modtime();

DROP TRIGGER IF EXISTS update_airship_custom_redirect_modtime ON airship_custom_redirect;
CREATE TRIGGER update_airship_custom_redirect_modtime
  BEFORE UPDATE ON airship_custom_redirect
  FOR EACH ROW EXECUTE PROCEDURE update_modtime();