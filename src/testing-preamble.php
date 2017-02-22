<?php
declare(strict_types=1);

use Airship\Engine\{
    Database,
    View,
    State
};

/**
 * @global array $active
 * @global Database[] $dbPool
 * @global State $state
 * @global View $lens
 */

require_once __DIR__ . '/preload.php';
require_once ROOT . '/bootstrap.php';
require_once ROOT . '/boot_final.php';
