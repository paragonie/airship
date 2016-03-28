CREATE TABLE bridge_announcments (
  announcementid BIGSERIAL PRIMARY KEY,
  title TEXT,
  contents TEXT,
  format TEXT,
  only_admins BOOLEAN DEFAULT FALSE,
  from_trusted_supplier BOOLEAN DEFAULT FALSE,
  created TIMESTAMP DEFAULT NOW()
);

CREATE TABLE bridge_announcements_dismiss (
  announcementid BIGINT REFERENCES bridge_announcements(announcementid),
  userid BIGINT REFERENCES airship_users(userid),
  created TIMESTAMP DEFAULT NOW()
);