CREATE TYPE type_airship_package AS ENUM ('Core', 'Cabin', 'Gadget', 'Motif');

CREATE TABLE IF NOT EXISTS airship_package_cache (
    packageid BIGSERIAL PRIMARY KEY,
    packagetype type_airship_package,
    supplier TEXT,
    name TEXT,
    installed BOOLEAN DEFAULT FALSE,
    current_version TEXT,
    skyport_metadata JSONB,
    created TIMESTAMP DEFAULT NOW(),
    modified TIMESTAMP DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS ON airship_package_cache (packagetype);
CREATE INDEX IF NOT EXISTS ON airship_package_cache (supplier);
CREATE INDEX IF NOT EXISTS ON airship_package_cache (name);
CREATE UNIQUE INDEX IF NOT EXISTS ON airship_package_cache(packagetype, supplier, name);

CREATE TABLE IF NOT EXISTS airship_package_versions (
    versionid BIGSERIAL PRIMARY KEY,
    package BIGINT REFERENCES airship_package_cache(packageid),
    version TEXT,
    checksum TEXT,
    commithash TEXT,
    date_released TIMESTAMP,
    treeupdateid BIGINT REFERENCES airship_tree_updates(treeupdateid),
    created TIMESTAMP DEFAULT NOW(),
    modified TIMESTAMP DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS ON airship_package_versions (version);
CREATE INDEX IF NOT EXISTS ON airship_package_versions (checksum);
CREATE UNIQUE INDEX IF NOT EXISTS ON airship_package_versions (package, version);

DROP TRIGGER IF EXISTS update_airship_package_versions_modtime ON airship_package_versions;
CREATE TRIGGER update_airship_package_versions_modtime
  BEFORE UPDATE ON airship_package_versions
  FOR EACH ROW EXECUTE PROCEDURE update_modtime();

DROP TRIGGER IF EXISTS update_airship_package_cache_modtime ON airship_package_cache;
CREATE TRIGGER update_airship_package_cache_modtime
  BEFORE UPDATE ON airship_package_cache
  FOR EACH ROW EXECUTE PROCEDURE update_modtime();