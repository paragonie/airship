CREATE TABLE airship_package_updates (
    updateid BIGSERIAL PRIMARY KEY,
    supplier TEXT,
    name TEXT,
    type TEXT,
    version TEXT,
    treeupdate BIGINT REFERENCES airship_tree_updates (treeupdateid),
    created TIMESTAMP DEFAULT NOW(),
    modified TIMESTAMP DEFAULT NOW()
);

DROP TRIGGER IF EXISTS update_airship_airship_package_updates_modtime ON airship_airship_package_updates;
CREATE TRIGGER update_airship_airship_package_updates_modtime
  BEFORE UPDATE ON airship_airship_package_updates
  FOR EACH ROW EXECUTE PROCEDURE update_modtime();
