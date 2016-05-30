<?php
declare(strict_types=1);

use \Airship\Engine\{
    Gears,
    State
};

/**
 * @global State $state
 */

// Always check for changes to channel keys before initiating update
require_once __DIR__.'/channel.php';

/**
 * Initialize the automatic updater service
 * @var \Airship\Engine\Continuum
 */
$autoUpdater = Gears::get('AutoUpdater', $hail);
if (IDE_HACKS) {
    // Just for the sake of auto-completion:
    $autoUpdater = new \Airship\Engine\Continuum($hail);
}

$state->logger->info('Automatic update started');
try {
    $autoUpdater->doUpdateCheck();
} catch (\Throwable $ex) {
    $state->logger->critical(
        'Tree update failed: ' . \get_class($ex),
        \Airship\throwableToArray($ex)
    );
    exit(255);
}
$state->logger->info('Automatic update concluded');

exit(0);