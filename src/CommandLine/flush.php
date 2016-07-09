<?php
declare(strict_types=1);

/**
 * Keyggdrasil updater -- either throw this in a cronjob or let it get
 * triggered every time a page loads after enough time has elapsed
 *
 * @global State $state
 */
\ignore_user_abort(true);
\set_time_limit(0);

require_once \dirname(__DIR__).'/bootstrap.php';


$db = \Airship\get_database();

$db->beginTransaction();

$db->exec("DROP TABLE airship_package_versions;");
$db->exec("DROP TABLE airship_package_cache;");
$db->exec("DROP TABLE airship_tree_updates;");

$db->exec("CREATE TABLE IF NOT EXISTS airship_tree_updates (
  treeupdateid BIGSERIAL PRIMARY KEY,
  channel TEXT,
  channelupdateid BIGINT,
  data TEXT,
  merkleroot TEXT,
  created TIMESTAMP DEFAULT NOW(),
  modified TIMESTAMP DEFAULT NOW()
);");
$db->exec("CREATE INDEX ON airship_tree_updates (channel);");
$db->exec("CREATE INDEX ON airship_tree_updates (channelupdateid);");
$db->exec("CREATE UNIQUE INDEX ON airship_tree_updates (channel, channelupdateid);");
$db->exec("CREATE INDEX ON airship_tree_updates (merkleroot);");

$db->exec("DROP TRIGGER IF EXISTS update_airship_tree_updates_modtime ON airship_tree_updates;");
$db->exec("CREATE TRIGGER update_airship_tree_updates_modtime
  BEFORE UPDATE ON airship_tree_updates
  FOR EACH ROW EXECUTE PROCEDURE update_modtime();");

$db->exec("CREATE TABLE IF NOT EXISTS airship_package_cache (
    packageid BIGSERIAL PRIMARY KEY,
    packagetype type_airship_package,
    supplier TEXT,
    name TEXT,
    installed BOOLEAN DEFAULT FALSE,
    current_version TEXT,
    skyport_metadata JSONB,
    created TIMESTAMP DEFAULT NOW(),
    modified TIMESTAMP DEFAULT NOW()
);");
$db->exec("CREATE INDEX ON airship_package_cache (packagetype);");
$db->exec("CREATE INDEX ON airship_package_cache (supplier);");
$db->exec("CREATE INDEX ON airship_package_cache (name);");
$db->exec("CREATE UNIQUE INDEX ON airship_package_cache(packagetype, supplier, name);");

$db->exec("CREATE TABLE IF NOT EXISTS airship_package_versions (
    versionid BIGSERIAL PRIMARY KEY,
    package BIGINT REFERENCES airship_package_cache(packageid),
    version TEXT,
    checksum TEXT,
    commithash TEXT,
    date_released TIMESTAMP,
    treeupdateid BIGINT REFERENCES airship_tree_updates(treeupdateid),
    created TIMESTAMP DEFAULT NOW(),
    modified TIMESTAMP DEFAULT NOW()
);");

$db->exec("CREATE INDEX ON airship_package_versions (version);");
$db->exec("CREATE INDEX ON airship_package_versions (checksum);");
$db->exec("CREATE UNIQUE INDEX ON airship_package_versions (package, version);");

$db->exec("DROP TRIGGER IF EXISTS update_airship_package_versions_modtime ON airship_package_versions;");
$db->exec("CREATE TRIGGER update_airship_package_versions_modtime
  BEFORE UPDATE ON airship_package_versions
  FOR EACH ROW EXECUTE PROCEDURE update_modtime();");

$db->exec("DROP TRIGGER IF EXISTS update_airship_package_cache_modtime ON airship_package_cache;");
$db->exec("CREATE TRIGGER update_airship_package_cache_modtime
  BEFORE UPDATE ON airship_package_cache
  FOR EACH ROW EXECUTE PROCEDURE update_modtime();");


$db->exec("INSERT INTO airship_tree_updates (channel, channelupdateid, data, merkleroot) VALUES
(
    'paragonie',
    1,
    '{\"action\":\"CREATE\",\"date_generated\":\"2016-06-04T16:00:00\",\"public_key\":\"1d9b44a5ec7be970dcb07efa81e661cb493f700953c0c26e5161b9cf0637e7f1\",\"supplier\":\"pragonie\",\"type\":\"master\",\"master\":null}',
    '99b4556c9506fd1742ca837e534553c9dcff5cdfae3ef57c74eb6175c6c8ffb9da04102a6a83c5139efd83c5e6f52cabc557ed0726652e041e214b8a677247ea'
)");
$db->exec("INSERT INTO airship_tree_updates (channel, channelupdateid, data, merkleroot) VALUES(
    'paragonie',
    2,
    '{\"action\":\"CREATE\",\"date_generated\":\"2016-06-04T16:05:00\",\"public_key\":\"6731558f53c6edf15c7cc1e439b15c18d6dfc1fd2c66f9fda8c56cfe7d37110b\",\"supplier\":\"pragonie\",\"type\":\"signing\",\"master\":\"{\\\"public_key\\\":\\\"1d9b44a5ec7be970dcb07efa81e661cb493f700953c0c26e5161b9cf0637e7f1\\\",\\\"signature\\\":\\\"017bb2dbe6fa75d3240f330be532bf8d9aced0654f257b5670edbd44c52f892459b5b314f095cd1df65346035a4b927dd4edbcfee677d4ebd5f861d6789fc301\\\"}\"}',
    '940c0456c19d3606b27c89d15a82523f8fdb83928b4d27e027058a279665b124afc7af4188098704058bf067f0349b32c9a8c7f244499623d5d9f7b6e1fa986d'
)");

$db->commit();
