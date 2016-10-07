<?php
declare(strict_types=1);

/**
 * This script runs when upgrading to v1.4.0 from an earlier version.
 * It adds the uniqueid column to the hull_blog_post_versions table then
 * retcons a uniqueid for each existing blog post version.
 */

$db = \Airship\get_database();

$db->exec('ALTER TABLE hull_blog_post_versions ADD uniqueid TEXT;');

foreach ($db->run('SELECT * FROM hull_blog_post_versions') as $ver) {
    // Get a unique ID:
    do {
        $unique = \Airship\uniqueId();
        $exists = $db->exists(
            'SELECT count(*) FROM hull_blog_post_versions WHERE uniqueid = ?',
            $unique
        );
    } while ($exists);

    // Now assign it.
    $db->update(
        'hull_blog_post_versions',
        [
            'uniqueid' =>
                $unique
        ],
        [
            'versionid' =>
                $ver['versionid']
        ]
    );
}

// Finally...
$db->exec('CREATE UNIQUE INDEX ON hull_blog_post_versions(uniqueid);');
