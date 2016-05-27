CREATE TABLE airship_package_updates (
    updateid BIGSERIAL PRIMARY KEY,
    supplier TEXT,
    name TEXT,
    type TEXT,
    version TEXT,
    checksum TEXT,
    treeupdate BIGINT REFERENCES airship_tree_updates (treeupdateid),
    created TIMESTAMP DEFAULT NOW(),
    modified TIMESTAMP DEFAULT NOW()
);

CREATE INDEX ON airship_package_updates (name);
CREATE INDEX ON airship_package_updates (supplier);
CREATE INDEX ON airship_package_updates (type);
CREATE INDEX ON airship_package_updates (checksum);
CREATE INDEX ON airship_package_updates (version);
CREATE INDEX ON airship_package_updates (type, supplier, name);
CREATE INDEX ON airship_package_updates (type, supplier, name, version);

DROP TRIGGER IF EXISTS update_airship_package_updates_modtime ON airship_package_updates;
CREATE TRIGGER update_airship_package_updates_modtime
  BEFORE UPDATE ON airship_package_updates
  FOR EACH ROW EXECUTE PROCEDURE update_modtime();
