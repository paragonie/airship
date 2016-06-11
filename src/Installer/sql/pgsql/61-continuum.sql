CREATE TYPE type_airship_package AS ENUM ('Core', 'Cabin', 'Gadget', 'Motif');

CREATE TABLE airship_package_cache (
    packageid BIGSERIAL PRIMARY KEY,
    packagetype type_airship_package,
    supplier TEXT,
    name TEXT,
    created TIMESTAMP DEFAULT NOW(),
    modified TIMESTAMP DEFAULT NOW()
);
CREATE INDEX ON airship_package_cache (packagetype);
CREATE INDEX ON airship_package_cache (supplier);
CREATE INDEX ON airship_package_cache (name);
CREATE UNIQUE INDEX ON airship_package_cache(packagetype, supplier, name);

CREATE TABLE airship_package_versions (
    versionid BIGSERIAL PRIMARY KEY,
    package BIGINT REFERENCES airship_package_cache(packageid),
    version TEXT,
    checksum TEXT,
    treeupdateid BIGINT REFERENCES airship_tree_updates(treeupdateid),
    created TIMESTAMP DEFAULT NOW(),
    modified TIMESTAMP DEFAULT NOW()
);

CREATE INDEX ON airship_package_versions (version);
CREATE INDEX ON airship_package_versions (checksum);
CREATE UNIQUE INDEX ON airship_package_versions (package, version);