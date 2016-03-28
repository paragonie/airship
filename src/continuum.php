<?php
declare(strict_types=1);
/**
 * Automatic update processing -- either throw this in a cronjob or let it get
 * triggered every time a page loads after enough time has elapsed
 */
\ignore_user_abort(true);
\set_time_limit(0);

require_once __DIR__.'/bootstrap.php';

$state = \Airship\Engine\State::instance();
/**
 * Initialize the automatic updater service
 */
$autoUpdater = \Airship\Engine\Gears::get(
    'AutoUpdater',
    $hail
);

$state->logger->info('Automatic update started');
try {
    $autoUpdater->doUpdateCheck(true);
} catch (\Throwable $ex) {
    $i = $ex->getCode();
    $state->logger->critical(
        'Automatic update failed: ' . \get_class($ex),
        \Airship\throwableToArray($ex)
    );
}
$state->logger->info('Automatic update concluded');

exit(0);