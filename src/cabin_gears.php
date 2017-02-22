<?php
declare(strict_types=1);

use Airship\Engine\Gears;

/**
 * Let's gearify the current cabin:
 *
 * e.g. Controller__IndexPage => \Airship\Cabin\Hull\Controller\IndexPage
 *
 */
$cabinGearsClosure = function() {
    foreach (['Controller', 'Model'] as $dir) {
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
