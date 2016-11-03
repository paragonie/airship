<?php
declare(strict_types=1);

require_once \dirname(__DIR__).'/src/bootstrap.php';

/**
 * This will nuke and reset the local update tree. Essentially,
 * only use it if Keyggdrasil ends up in an invalid state (e.g.
 * hard fork).
 */

$db = \Airship\get_database();

$db->beginTransaction();

$db->query('TRUNCATE TABLE airship_tree_updates;');

$query = $db->query(<<<EOSQL
INSERT INTO airship_tree_updates (channel, channelupdateid, data, merkleroot) VALUES
(
    'paragonie',
    1,
    '{"action":"CREATE","date_generated":"2016-06-04T16:00:00","public_key":"1d9b44a5ec7be970dcb07efa81e661cb493f700953c0c26e5161b9cf0637e7f1","supplier":"pragonie","type":"master","master":null}',
    '99b4556c9506fd1742ca837e534553c9dcff5cdfae3ef57c74eb6175c6c8ffb9da04102a6a83c5139efd83c5e6f52cabc557ed0726652e041e214b8a677247ea'
),
(
'paragonie',
    2,
    '{"action":"CREATE","date_generated":"2016-06-04T16:05:00","public_key":"6731558f53c6edf15c7cc1e439b15c18d6dfc1fd2c66f9fda8c56cfe7d37110b","supplier":"pragonie","type":"signing","master":"{\"public_key\":\"1d9b44a5ec7be970dcb07efa81e661cb493f700953c0c26e5161b9cf0637e7f1\",\"signature\":\"017bb2dbe6fa75d3240f330be532bf8d9aced0654f257b5670edbd44c52f892459b5b314f095cd1df65346035a4b927dd4edbcfee677d4ebd5f861d6789fc301\"}"}',
    '940c0456c19d3606b27c89d15a82523f8fdb83928b4d27e027058a279665b124afc7af4188098704058bf067f0349b32c9a8c7f244499623d5d9f7b6e1fa986d'
);
EOSQL
);


// Reset the sequence value:
$db->query("SELECT setval('airship_tree_updates_treeupdateid_seq', 2);");

/* More generally:
SELECT setval(
    'airship_tree_updates_treeupdateid_seq',
    COALESCE(
        (SELECT MAX(treeupdateid) FROM airship_tree_updates),
        1
    )
);
*/

if ($db->commit()) {
    echo 'OK', PHP_EOL;
    exit(0);
}
