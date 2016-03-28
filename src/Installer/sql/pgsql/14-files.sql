CREATE TABLE airship_dirs (
  directoryid BIGSERIAL PRIMARY KEY,
  parent BIGINT NULL REFERENCES airship_dirs (directoryid),
  cabin TEXT,
  name TEXT NOT NULL,
  created TIMESTAMP DEFAULT NOW(),
  modified TIMESTAMP DEFAULT NOW(),
  CHECK(LENGTH(name) > 0),
  UNIQUE(cabin, parent, name)
);

CREATE TABLE airship_files (
  fileid BIGSERIAL PRIMARY KEY,
  filename TEXT,
  type TEXT,
  realname TEXT,
  checksum TEXT,
  uploaded_by BIGINT NULL REFERENCES airship_users (userid),
  directory BIGINT NULL REFERENCES airship_dirs (directoryid),
  cabin TEXT NULL,
  created TIMESTAMP DEFAULT NOW(),
  modified TIMESTAMP DEFAULT NOW(),
  UNIQUE(directory, filename),
  CHECK((directory IS NULL) != (cabin IS NULL))
);
