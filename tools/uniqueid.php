<?php
declare(strict_types=1);

require_once \dirname(__DIR__).'/src/bootstrap.php';

/**
 * This gives each user a uniqueid if they do not already have one.
 *
 * It *should* be safe to remove, but we're holding off on doing that
 * until version 2.0.0.
 */

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
