<?php
declare(strict_types=1);

use Airship\Engine\{
    AutoPilot,
    Gears,
    State
};

/**
 * This loads the Cabin configuration, and selects the active cabin.
 *
 * @global State $state
 */

$ap = Gears::getName('AutoPilot');

// Needed for IDE code completion:
if (IDE_HACKS) {
    $ap = new AutoPilot();
}

/**
 * Cache the cabin configuration
 */
$cabinDisabled = false;
if (\file_exists(ROOT . '/tmp/cache/cabin_data.json')) {
    // Load the cabins from cache
    $cabinConfig = \Airship\loadJSON(ROOT . '/tmp/cache/cabin_data.json');
    foreach ($cabinConfig['cabins'] as $key => $cabin) {
        if ($ap::isActiveCabinKey($key, !empty($cabin['https']))) {
            $state->active_cabin = $key;
            if ($cabin['enabled']) {
                $cabinDisabled = true;
            }
            break;
        }
    }
    $state->cabins = $cabinConfig['cabins'];
} else {
    // Load the cabins, rebuild the cache
    $cabins = \Airship\loadJSON(ROOT . '/config/cabins.json');
    $active_cabin = null;
    foreach ($cabins as $key => $cabin) {
        try {
            $cabinName = !empty($cabin['namespace'])
                ? $cabin['namespace']
                : $cabin['name'];

            $cabin['data'] = \Airship\loadJSON(
                \implode(
                    '/',
                    [
                        ROOT,
                        'Cabin',
                        $cabinName,
                        'manifest.json'
                    ]
                )
            );

            // Link configuration directory:
            $startLink = \implode(
                '/',
                [
                    ROOT,
                    'config',
                    'Cabin',
                    $cabinName
                ]
            );
            if (!\is_link($startLink)) {
                $endLink = \implode(
                    '/',
                    [
                        ROOT,
                        'Cabin',
                        $cabinName,
                        'config'
                    ]
                );
                \symlink($endLink, $startLink);
            }

            // Link configuration template:
            $startLink = \implode(
                '/',
                [
                    ROOT,
                    'config',
                    'templates',
                    'Cabin',
                    $cabinName
                ]
            );
            if (!\is_link($startLink)) {
                $endLink = \implode(
                    '/',
                    [
                        ROOT,
                        'Cabin',
                        $cabinName,
                        'config',
                        'templates'
                    ]
                );
                \symlink($endLink, $startLink);
            }

            // Expose common template snippets to the template loader:
            $startLink = \implode(
                '/',
                [
                    ROOT,
                    'Cabin',
                    $cabinName,
                    'View',
                    'common'
                ]
            );
            if (!\is_link($startLink)) {
                \symlink(ROOT . '/common', $startLink);
            }
        } catch (Exception $ex) {
            $cabin['data'] = null;
        }
        if (empty($active_cabin) && $ap::isActiveCabinKey($key)) {
            if ($cabin['enabled']) {
                $active_cabin = $key;
            } else {
                $cabinDisabled = true;
            }
        }
        $cabins[$key] = $cabin;
    }

    if ($cabinDisabled) {
        \http_response_code(404);
        echo \file_get_contents(
            __DIR__ . '/error_pages/no-cabin.html'
        );
        exit(1);
    }
    if (empty($active_cabin)) {
        $k = \array_keys($cabins);
        $active_cabin = \array_pop($k);
        unset($k);
    }
    $state->active_cabin = $active_cabin;

    $state->cabins = $cabins;
    \Airship\saveJSON(
        ROOT.'/tmp/cache/cabin_data.json',
        [
            'cabins' => $cabins
        ]
    );
}
