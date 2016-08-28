<?php
declare(strict_types=1);

use Airship\Engine\State;

/**
 * This script runs when upgrading to v1.3.0 from an earlier version.
 * It deletes the old symlinks used for resolving Motif templates.
 * The bootstrapping process is sufficient to restore them.
 */

$state = State::instance();

foreach ($state->cabins as $cabin) {
    $cabinName = (string) ($cabin['namespace'] ?? $cabin['name']);
    foreach (\glob(ROOT . '/Cabin/' . $cabinName . '/Lens/motif/*') as $f) {
        $endPiece = \Airship\path_to_filename($f);
        if (\is_link($f)) {
            \unlink($f);
        }
    }
}
