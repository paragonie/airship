<?php
declare(strict_types=1);

use Airship\Engine\{
    AutoPilot,
    Database,
    Gears
};

// Stuff to load after the update check has concluded or been skipped.

/**
 * Let's load the latest gear for our autoloader
 *
 * @global array $active
 * @global Database[] $dbPool
 */
define('CABIN_NAME', (string) $active['name']);
define('CABIN_DIR', ROOT . '/Cabin/' . $active['name']);

// Turn all of this cabins' Landings and Blueprints into gears:
require ROOT . '/cabin_gears.php';

$lens->addGlobal('ACTIVE_CABIN', \CABIN_NAME);

$autoPilot = Gears::get(
    'AutoPilot',
    $active,
    $lens,
    $dbPool
);

if ($autoPilot instanceof AutoPilot) {
    $autoPilot->setActiveCabin(
        $active,
        $state->active_cabin
    );
}

// Load everything else:
require ROOT . '/symlinks.php';
require ROOT . '/motifs.php';
require ROOT . '/security.php';
require ROOT . '/email.php';

$state->autoPilot = $autoPilot;
