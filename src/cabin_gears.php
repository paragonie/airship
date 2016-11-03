<?php
declare(strict_types=1);

use Airship\Engine\Gears;

/**
 * Let's gearify the current cabin:
 *
 * e.g. Landing__IndexPage => \Airship\Cabin\Hull\Landing\IndexPage
 *
 */
$cabinGearsClosure = function() {
    foreach (['Landing', 'Blueprint'] as $dir) {
        foreach (\glob(CABIN_DIR . '/' . $dir . '/*.php') as $landing) {
            $filename = \Airship\path_to_filename($landing, true);
            if ($filename === 'init_gear') {
                continue;
            }
            Gears::lazyForge(
                $dir . '__' . $filename,
                '\\Airship\\Cabin\\' . CABIN_NAME . '\\' . $dir . '\\' . $filename
            );
        }
    }
};
$cabinGearsClosure();
unset($cabinGearsClosure);
