<?php
declare(strict_types=1);
if (PHP_VERSION_ID < 70000) {
    die("Airship requires PHP 7.0.0 or newer. You are running PHP ".PHP_VERSION);
}
if (!extension_loaded('libsodium')) {
    die("Airship requires Libsodium.");
}
define('IDE_HACKS', false);

/**
 * 1. Define come constants
 */
if (!defined('ROOT')) {
    \define('ROOT', __DIR__);
}
\define(
    'AIRSHIP_UPLOADS',
    ROOT . DIRECTORY_SEPARATOR .
        'files' . DIRECTORY_SEPARATOR .
        'uploaded' . DIRECTORY_SEPARATOR
);
if (!defined('ISCLI')) {
    define('ISCLI', PHP_SAPI === 'cli');
}
if (ISCLI) {
    if (isset($argc)) {
        $_SERVER['REQUEST_URI'] = $argc > 1
            ? $argv[1]
            : '/';
    } elseif(empty($_SERVER['REQUEST_URI'])) {
        $_SERVER['REQUEST_URI'] = '';
    }
} elseif (\file_exists(ROOT.DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'site_down.txt')) {
    // There might be an automatic update in progress!
    // Let's give it up to 15 seconds, but only as much time as is needed.
    $iter = 0;
    do {
        if (!\file_exists(ROOT.DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'site_down.txt')) {
            break;
        }
        \usleep(100);
        ++$iter;
    } while($iter < 15000);

    \clearstatcache();
    // If we're still in the middle of that process, let's not load anything else:
    if (\file_exists(ROOT.DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'site_down.txt')) {
        echo 'This Airship is currently being repaired. Please try again later.', "\n";
        exit(255);
    }
}
if (!isset($_SERVER['REQUEST_URI'])) {
    $_SERVER['REQUEST_URI'] = '';
}

/**
 * 2. Load the Airship functions
 */
require_once ROOT.'/Airship.php';

/**
 * 3. Let's autoload the composer packages
 */
require_once \dirname(ROOT).'/vendor/autoload.php';

// Let's also make sure we're using a good version of libsodium
if (!\ParagonIE\Halite\Halite::isLibsodiumSetupCorrectly()) {
    die("Airship requires libsodium 1.0.9 or newer (with a stable version of the PHP bindings).");
}

/**
 * 4. Autoload the Engine files
 */
\Airship\autoload('Airship\\Alerts', '~/Alerts');
\Airship\autoload('Airship\\Engine', '~/Engine');

/**
 * 5. Load up the registry singleton for latest types
 */
$state = \Airship\Engine\State::instance();

require_once ROOT.'/gear_init.php';

/**
 * 6. Load the global functions
 */
require_once ROOT.'/global_functions.php';
require_once ROOT.'/lens_functions.php';

/**
 * 7. Load all of the cryptography keys
 */
require_once ROOT.'/keys.php';
