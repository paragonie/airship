<?php
declare(strict_types=1);

use \Airship\Engine\{
    AutoPilot,
    Gears,
    State
};

/**
 * @global State $state
 */

$ap = Gears::getName('AutoPilot');
if (IDE_HACKS) {
    $ap = new AutoPilot();
}

/**
 * Cache the cabin configuration
 */
if (\file_exists(ROOT.'/tmp/cache/cabin_data.json')) {
    // Load the cabins from cache
    $config = \Airship\loadJSON(ROOT.'/tmp/cache/cabin_data.json');
    foreach ($config['cabins'] as $key => $cabin) {
        if ($ap::isActiveCabinKey($key, !empty($cabin['https']))) {
            $state->active_cabin = $key;
            break;
        }
    }
    $state->cabins = $config['cabins'];
} else {
    // Load the cabins, rebuild the cache
    $cabins = \Airship\loadJSON(ROOT.'/config/cabins.json');
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
        } catch (Exception $ex) {
            $cabin['data'] = null;
        }
        if (empty($active_cabin) && $ap::isActiveCabinKey($key)) {
            $active_cabin = $key;
        }
        $cabins[$key] = $cabin;
    }
    if (empty($active_cabin)) {
        $k = \array_keys($cabins);
        $active_cabin = \array_pop($k);
        unset($k);
    }
    $state->active_cabin = $active_cabin;

    $state->cabins = $cabins;
    \file_put_contents(
        ROOT.'/tmp/cache/cabin_data.json',
        \json_encode(
            [
                'cabins' => $cabins
            ],
            JSON_PRETTY_PRINT
        )
    );
}
