<?php
declare(strict_types=1);

use \Airship\Engine\State;

/**
 * @global array $active The active cabin configuration
 * @const string CABIN_DIR
 * @global State $state
 */

// Let's make sure we populate the symlinks
if (\is_dir(CABIN_DIR.'/public')) {
    if (!\is_link(ROOT.'/public/static/'.$active['name'])) {
        // Remove copies, we only allow symlinks in static
        if (\is_dir(ROOT.'/public/static/'.$active['name'])) {
            \rmdir(ROOT.'/public/static/'.$active['name']);
        } elseif (\file_exists(ROOT.'/public/static/'.$active['name'])) {
            \unlink(ROOT.'/public/static/'.$active['name']);
        }
        
        // Create a symlink from public/static/* to Cabin/*/public
        @\symlink(
            CABIN_DIR.'/public',
            ROOT.'/public/static/'.$active['name']
        );
    }
}

// Let's load the default cargo modules
if (\is_dir(CABIN_DIR.'/Lens/cargo')) {
    if (\file_exists(ROOT.'/tmp/cache/cargo-'.$active['name'].'.cache.json')) {
        $data = Airship\loadJSON(ROOT.'/tmp/cache/cargo-'.$active['name'].'.cache.json');
        $state->cargo = $data;
    } else {
        $dir = \getcwd();
        \chdir(CABIN_DIR.'/Lens');
        foreach (\Airship\list_all_files('cargo', 'twig') as $cargo) {
            $idx = \str_replace(
                ['__', '/'],
                ['',   '__'],
                \substr($cargo, 6, -5)
            );
            \Airship\Engine\Gadgets::loadCargo($idx, $cargo);
        }
        \chdir($dir);
        
        // Store the cache file
        \file_put_contents(
            ROOT.'/tmp/cache/cargo-'.$active['name'].'.cache.json',
            \json_encode($state->cargo, JSON_PRETTY_PRINT)
        );
    }
}
