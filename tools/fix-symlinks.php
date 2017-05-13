<?php
declare(strict_types=1);

require_once \dirname(__DIR__).'/src/bootstrap.php';

/* This tool just cleans up symlinks.
 *
 * This should be run if you are upgrading from version 1.x to 2.x.
 */

# View/common
foreach (\glob(ROOT . '/Cabin/*') as $cabinName) {
    if (!\is_dir($cabinName)) {
        continue;
    }
    if (!\is_dir($cabinName . '/View')) {
        continue;
    }
    if (\file_exists($cabinName . '/View/common')) {
        \unlink($cabinName . '/View/common');
    }
    \symlink(ROOT . '/common', $cabinName . '/View/common');
}

