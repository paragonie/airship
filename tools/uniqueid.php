<?php
require_once \dirname(__DIR__).'/src/bootstrap.php';

$db = \Airship\get_database();

foreach ($db->run('SELECT * FROM airship_users WHERE uniqueid IS NULL OR LENGTH(uniqueid) < 24') as $r) {
    $db->update(
        'airship_users', [
            'uniqueid' => \Airship\uniqueId()
        ], [
            'userid' => $r['userid']
        ]
    );
    echo "{$r['userid']}\n";
}
