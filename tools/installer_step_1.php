<?php
declare(strict_types=1);

use \ParagonIE\ConstantTime\Base64UrlSafe;

/**
 * Used in automated deployment scripts. Generates, stores, then echos a random
 * PostgreSQL password, then sets up the web-based installer for step 2.
 */
require_once \dirname(__DIR__) . '/src/bootstrap.php';

$password = Base64UrlSafe::encode(\random_bytes(33));

\Airship\saveJSON(
    \dirname(__DIR__) . '/src/tmp/installing.json',
    [
        'step' => 2,
        'database' => [
            [
                [
                    'driver' => 'pgsql',
                    'host' => $argv[1] ?? 'localhost',
                    'port' => $argv[2] ?? 5432,
                    'database' => 'airship',
                    'username' => 'airship',
                    'password' => $password
                ]
            ]
        ]
    ]
);

echo $password;