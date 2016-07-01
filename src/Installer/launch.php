<?php
declare(strict_types=1);

use \Airship\Alerts\FileSystem\FileNotFound;
use \ParagonIE\ConstantTime\Base64UrlSafe;

\error_reporting(E_ALL);
if (PHP_MAJOR_VERSION < 7) {
    die("Airship requires PHP 7.");
}
if (!extension_loaded('libsodium')) {
    die("Airship requires Libsodium.");
}
if (!\defined('IDE_HACKS')) {
    define('IDE_HACKS', false);
}
if (!\session_id()) {
    \session_start();
}

/**
 * 1. Define come constants
 */
if (!defined('ROOT')) {
    define('ROOT', \dirname(__DIR__));
}
if (!defined('ISCLI')) {
    define('ISCLI', PHP_SAPI === 'cli');
}
if (ISCLI) {
    if (isset($argc)) {
        $_SERVER['REQUEST_URI'] = $argc > 1 
            ? $argv[1] 
            : '/';
    } elseif(empty($_SERVER['REQUEST_URI'])) {
        $_SERVER['REQUEST_URI'] = '';
    }
}

/**
 * 2. Load the Airship functions
 */
require_once ROOT.'/Airship.php';
require_once __DIR__ . '/motifs.php';

/**
 * 3. Let's autoload the composer packages
 */
require_once \dirname(ROOT).'/vendor/autoload.php';

/**
 * 4. Autoload the Engine files
 */
\Airship\autoload('Airship\\Alerts', '~/Alerts');
\Airship\autoload('Airship\\Engine', '~/Engine');
\Airship\autoload('Airship\\Installer', '~/Installer');
$state = \Airship\Engine\State::instance();

require_once ROOT.'/gear_init.php';

/**
 * 5. Load the global functions
 */
require_once ROOT.'/global_functions.php';
require_once ROOT.'/lens_functions.php';

if (ISCLI) {
    if ($argc < 2) {
        echo "\n",
            'Error: No argument passed to command line interface.',
            "\n\n";
        exit(1);
    }

    $cli = new \Airship\Installer\Commands($argv);
    exit(0);
}

$twigLoader = new \Twig_Loader_Filesystem(
    ROOT.'/Installer/skins'
);
$twigEnv = new \Twig_Environment($twigLoader);


// Expose PHP's built-in functions as a filter
$twigEnv->addFilter(
    new Twig_SimpleFilter('addslashes', 'addslashes')
);
$twigEnv->addFilter(
    new Twig_SimpleFilter('preg_quote', 'preg_quote')
);
$twigEnv->addFilter(
    new Twig_SimpleFilter('ceil', 'ceil')
);
$twigEnv->addFilter(
    new Twig_SimpleFilter('floor', 'floor')
);

$twigEnv->addFilter(
    new Twig_SimpleFilter(
        'cachebust', 
        function ($relative_path) {
            if ($relative_path[0] !== '/') {
                $relative_path = '/' . $relative_path;
            }
            $absolute = $_SERVER['DOCUMENT_ROOT'] . $relative_path;
            if (\is_readable($absolute)) {
                return $relative_path.'?'.Base64UrlSafe::encode(
                    \Sodium\crypto_generichash(
                        \file_get_contents($absolute).\filemtime($absolute)
                    )
                );
            }
            return $relative_path.'?404NotFound';
        }
    )
);

$twigEnv->addFunction(
    new Twig_SimpleFunction(
        'form_token',
        function($lockTo = '') {
            static $csrf = null;
            if ($csrf === null) {
                $csrf = new \Airship\Engine\Security\CSRF;
            }
            return $csrf->insertToken($lockTo);
        }
    )
);

$twigEnv->addFunction(
    new Twig_SimpleFunction(
        '__',
        function(string $str = '') {
            // Not translating here.
            return $str;
        }
    )
);

$twigEnv->addFunction(
    new Twig_SimpleFunction(
        'get_loaded_extensions',
        function () {
            return \get_loaded_extensions();
        }
    )
);
    
$twigEnv->addGlobal('SERVER', $_SERVER);

require_once ROOT.'/keys.php';
try {
    $step = \Airship\loadJSON(ROOT . '/tmp/installing.json');
    if (empty($step)) {
        \file_put_contents(ROOT . '/tmp/installing.json', '[]');
        $step = [];
    }
} catch (FileNotFound $e) {
    \file_put_contents(ROOT . '/tmp/installing.json', '[]');
    try {
        $step = \Airship\loadJSON(ROOT . '/tmp/installing.json');
    } catch (FileNotFound $e) {
        die("Cannot create " . ROOT . '/tmp/installing.json');
    }
}

require_once ROOT . "/Installer/symlinks.php";

$installer = new \Airship\Installer\Install(
    $twigEnv,
    $step
);
$installer->currentStep();