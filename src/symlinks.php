<?php
declare(strict_types=1);

use \Airship\Engine\{
    Gadgets,
    State
};

/**
 * @global array $active The active cabin configuration
 * @const string CABIN_DIR
 * @global State $state
 */

// Let's make sure we populate the symlinks
if (\is_dir(CABIN_DIR . '/public')) {
    $link = ROOT . '/public/static/' . $active['name'];
    if (!\is_link($link)) {
        // Remove copies, we only allow symlinks in static
        if (\is_dir($link)) {
            \rmdir($link);
        } elseif (\file_exists($link)) {
            \unlink($link);
        }
        
        // Create a symlink from public/static/* to Cabin/*/public
        /** @noinspection PhpUsageOfSilenceOperatorInspection */
        @\symlink(
            CABIN_DIR.'/public',
            ROOT.'/public/static/'.$active['name']
        );
    }
}

// Let's load the default cargo modules
if (\is_dir(CABIN_DIR.'/Lens/cargo')) {
    $cargoCacheFile = ROOT.'/tmp/cache/cargo-'.$active['name'].'.cache.json';
    if (\file_exists($cargoCacheFile)) {
        $data = Airship\loadJSON($cargoCacheFile);
        $state->cargo = $data;
    } else {
        $dir = \getcwd();
        \chdir(CABIN_DIR . '/Lens');
        foreach (\Airship\list_all_files('cargo', 'twig') as $cargo) {
            $idx = \str_replace(
                ['__', '/'],
                ['',   '__'],
                \substr($cargo, 6, -5)
            );
            Gadgets::loadCargo($idx, $cargo);
        }
        \chdir($dir);
        
        // Store the cache file
        \Airship\saveJSON(
            $cargoCacheFile,
            $state->cargo
        );
    }
}
