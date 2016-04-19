CREATE TABLE airship_key_updates (
  keyupdateid BIGSERIAL PRIMARY KEY,
  channel TEXT,
  channelupdateid BIGINT,
  data TEXT,
  merkleroot TEXT,
  created TIMESTAMP DEFAULT NOW(),
  modified TIMESTAMP DEFAULT NOW()
);
CREATE INDEX ON airship_key_updates (channel);
CREATE INDEX ON airship_key_updates (channelupdateid);
CREATE UNIQUE INDEX ON airship_key_updates (channel, channelupdateid);
CREATE INDEX ON airship_key_updates (merkleroot);
