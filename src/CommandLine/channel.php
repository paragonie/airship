<?php
declare(strict_types=1);

use Airship\Engine\{
    Gears,
    Hail,
    Keyggdrasil,
    State
};

/**
 * Keyggdrasil updater -- either throw this in a cronjob or let it get
 * triggered every time a page loads after enough time has elapsed
 *
 * @global State $state
 * @global Hail $hail
 */
\ignore_user_abort(true);
\set_time_limit(0);

require_once \dirname(__DIR__).'/bootstrap.php';

if (\is_readable(ROOT . '/config/databases.json')) {
    /**
     * Initialize the channel updater service
     */
    $channels = \Airship\loadJSON(ROOT . '/config/channels.json');
    $database = \Airship\get_database();

    $state->logger->info('Keyggdrasil started');
    $keyUpdater = Gears::get('TreeUpdater', $hail, $database, $channels);
    if (IDE_HACKS) {
        $keyUpdater = new Keyggdrasil($hail, $database, $channels);
    }
    $keyUpdater->doUpdate();
    $state->logger->info('Keyggdrasil concluded');
} else {
    // We can't update keys without a place to persist the changes
}
