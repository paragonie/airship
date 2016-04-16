CREATE TABLE airship_key_peers (
  peerid BIGSERIAL PRIMARY KEY,
  name TEXT,
  channel TEXT,
  domain TEXT,
  path TEXT,
  publickey TEXT,
  created TIMESTAMP DEFAULT NOW(),
  modified TIMESTAMP DEFAULT NOW()
);
CREATE INDEX ON airship_key_peers (channel);
CREATE INDEX ON airship_key_peers (domain);
CREATE INDEX ON airship_key_peers (path);
CREATE INDEX ON airship_key_peers (publickey);
CREATE UNIQUE INDEX ON airship_key_peers (channel, domain, path, publickey);

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
