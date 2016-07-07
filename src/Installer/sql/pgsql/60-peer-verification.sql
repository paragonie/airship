CREATE TABLE IF NOT EXISTS airship_tree_updates (
  treeupdateid BIGSERIAL PRIMARY KEY,
  channel TEXT,
  channelupdateid BIGINT,
  data TEXT,
  merkleroot TEXT,
  created TIMESTAMP DEFAULT NOW(),
  modified TIMESTAMP DEFAULT NOW()
);
CREATE INDEX  IF NOT EXISTSON airship_tree_updates (channel);
CREATE INDEX  IF NOT EXISTSON airship_tree_updates (channelupdateid);
CREATE UNIQUE INDEX  IF NOT EXISTSON airship_tree_updates (channel, channelupdateid);
CREATE INDEX  IF NOT EXISTSON airship_tree_updates (merkleroot);

DROP TRIGGER IF EXISTS update_airship_tree_updates_modtime ON airship_tree_updates;
CREATE TRIGGER update_airship_tree_updates_modtime
  BEFORE UPDATE ON airship_tree_updates
  FOR EACH ROW EXECUTE PROCEDURE update_modtime();