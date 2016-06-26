CREATE TABLE bridge_announcements (
  announcementid BIGSERIAL PRIMARY KEY,
  uniqueid TEXT,
  title TEXT,
  contents TEXT,
  format TEXT,
  only_admins BOOLEAN DEFAULT FALSE,
  from_trusted_supplier BOOLEAN DEFAULT FALSE,
  created TIMESTAMP DEFAULT NOW()
);
CREATE UNIQUE INDEX ON bridge_announcements (uniqueid);

CREATE TABLE bridge_announcements_dismiss (
  announcementid BIGINT REFERENCES bridge_announcements(announcementid),
  userid BIGINT REFERENCES airship_users(userid),
  created TIMESTAMP DEFAULT NOW()
);
CREATE UNIQUE INDEX ON bridge_announcements_dismiss (announcementid, userid);