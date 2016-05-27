<?php
declare(strict_types=1);
/**
 * Cache the cabin configuration
 */
if (\file_exists(ROOT.'/tmp/cache/cabin_data.json')) {
    // Load the cabins
    $config = \Airship\loadJSON(ROOT.'/tmp/cache/cabin_data.json');
    foreach ($config['cabins'] as $key => $cabin) {
        if (\Airship\Engine\AutoPilot::isActiveCabinKey($key, $cabin['https'])) {
            $state->active_cabin = $key;
            break;
        }
    }
    $state->cabins = $config['cabins'];
} else {
    // Load the cabins
    $cabins = \Airship\loadJSON(ROOT.'/config/cabins.json');
    $active_cabin = null;
    foreach ($cabins as $key => $cabin) {
        try {
            if ($cabin['supplier']) {
                $cabin['data'] = \Airship\loadJSON(
                    \implode('/',
                        [
                            ROOT,
                            'Cabin',
                            // Adheres to a vendor name-spacing:
                            $cabin['supplier'] . '_' . $cabin['name'],
                            'manifest.json'
                        ]
                    )
                );
            } else {
                $cabin['data'] = \Airship\loadJSON(
                    \implode('/',
                        [
                            ROOT,
                            'Cabin',
                            $cabin['name'],
                            'manifest.json'
                        ]
                    )
                );
            }
        } catch (Exception $ex) {
            $cabin['data'] = null;
        }
        if (empty($active_cabin) && \Airship\Engine\AutoPilot::isActiveCabinKey($key)) {
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
