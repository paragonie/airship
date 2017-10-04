<?php
declare(strict_types=1);

use Airship\Engine\{
    Gears,
    State
};
use GuzzleHttp\Client as HTTPClient;

if (!\defined('ROOT')) {
    require_once __DIR__.'/preload.php';
}
/**
 * This is the regular bootstrapping script for Airship. If you write your own
 * API endpoint different from public/index.php, make sure you require_once
 * this file.
 *
 * @global State $state
 */

/**
 * 1. Set up the cabins.
 */
require_once ROOT . '/cabins.php';
\Airship\autoload('\\Airship\\Cabin', '~/Cabin');

/**
 * 2. Let's bootstrap the routes and other configuration
 *    for the current cabin (set in cabins.php)
 */
if (!$state->active_cabin) {
    \http_response_code(404);
    echo \file_get_contents(
        __DIR__ . '/error_pages/no-cabin.html'
    );
    exit(1);
}
$active = $state->cabins[$state->active_cabin];
$state->lang = isset($active['lang']) 
    ? $active['lang']
    : 'en-us'; // default

/**
 * 3. Defer execution if we are updating this Cabin:
 */
if (!\ISCLI) {
    $cabinFile = \implode(DIRECTORY_SEPARATOR, [
        ROOT,
        'tmp',
        'cabin.' . $active['name'] . '.offline.txt'
    ]);
    if (\file_exists($cabinFile)) {
        // There might be an automatic update in progress!
        // Let's give it up to 15 seconds, but only as much time as is needed.
        $iter = 0;
        do {
            if (!\file_exists($cabinFile)) {
                break;
            }
            \usleep(1000);
            ++$iter;
        } while($iter < 15000);

        \clearstatcache();
        // If we're still in the middle of that process, let's not load anything else:
        if (\file_exists($cabinFile)) {
            echo \__('This Airship is currently docked for routine maintenance. Please try again later.'), "\n";
            exit(255);
        }
    }
}

// Let's set the current language:
$lang = \preg_replace_callback(
    '#([A-Za-z]+)\-([a-zA-Z]+)#',
        function($matches) {
            return \strtolower($matches[1]).'_'.\strtoupper($matches[2]);
        },
        $state->lang
    ) . '.UTF-8';
\putenv('LANG='.$lang);

// Overload the active template
if (isset($active['data']['base_template'])) {
    $state->base_template = $active['data']['base_template'];
} else {
    $state->base_template = 'base.twig';
}

// Let's load the universal configuration settings
$universal = \Airship\loadJSON(ROOT . '/config/universal.json');
$state->universal = $universal;

// Let's start our session:
require_once ROOT . '/session.php';

// This loads templates for the template engine
$twigLoader = new \Twig_Loader_Filesystem(
    ROOT . '/Cabin/' . $active['name'] . '/View'
);

$lensLoad = [];

// Load all the gadgets, which can act on $twigLoader
include ROOT . '/config/gadgets.php';

// Twig configuration options:
$twigOpts = [
    // Defaults to 'html' strategy:
    'autoescape' => true,
    'debug' => $state->universal['debug']
];
if (!empty($state->universal['twig-cache'])) {
    $twigOpts['cache'] = ROOT . '/tmp/cache/twig';
}

$twigEnv = new \Twig_Environment($twigLoader, $twigOpts);
if ($state->universal['debug']) {
    $twigEnv->addExtension(new \Twig_Extension_Debug());
}
$lens = Gears::get('View', $twigEnv);

// Load the View configuration
include ROOT . '/config/view.php';

// Load the Cabin-specific filters etc, if applicable:
if (\file_exists(ROOT . '/Cabin/' . $active['name'] . '/view.php')) {
    include ROOT . '/Cabin/' . $active['name'] . '/view.php';
}

// Load the template variables for this Cabin:
if (\file_exists(ROOT.'/config/Cabin/' . $active['name'] . '/twig_vars.json')) {
    $_settings = \Airship\loadJSON(
        ROOT.'/config/Cabin/' . $active['name'] . '/twig_vars.json'
    );
    $lens->addGlobal(
        'SETTINGS',
        $_settings
    );
}

// Now let's load all the lens.php files, which are added by Gadgets:
foreach ($lensLoad as $incl) {
    include $incl;
}

/**
 * Let's load up the databases
 */
$dbPool = [];
require ROOT . '/database.php';

// Airship manifest:
$manifest = \Airship\loadJSON(ROOT . '/config/manifest.json');
$state->manifest = $manifest;

$htmlpurifier = new \HTMLPurifier(
    \HTMLPurifier_Config::createDefault()
);
$state->HTMLPurifier = $htmlpurifier;

/**
 * Load up all of the keys used by the application:
 */
require_once ROOT . '/keys.php';

/**
 * Set up the logger
 */
require_once ROOT . '/config/logger.php';

/**
 * Automatic security updates
 */
$hail = Gears::get(
    'Hail',
    new HTTPClient($state->universal['guzzle'])
);
$state->hail = $hail;
