<?php
declare(strict_types=1);

use Airship\Engine\{
    Continuum,
    Gears,
    Hail,
    State
};

/**
 * @global State $state
 * @global Hail $hail
 */

// Always check for changes to channel keys before initiating update
require_once __DIR__.'/channel.php';

/**
 * Initialize the automatic updater service
 * @var \Airship\Engine\Continuum
 */
$autoUpdater = Gears::get('AutoUpdater', $hail);
if (!($autoUpdater instanceof Continuum)) {
    throw new \TypeError(
        \trk('errors.type.wrong_class', Continuum::class)
    );
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
\Airship\clear_cache();

exit(0);
