<?php
declare(strict_types=1);

use Airship\Engine\{
    AutoPilot,
    Database,
    Gears,
    Cache\File as FileCache,
    Cache\SharedMemory as MemoryCache,
    State
};
use Psr\Log\LogLevel;

/**
 * @global array $active
 * @global Database[] $dbPool
 * @global State $state
 */

// Are we still installing?
/** @noinspection PhpUsageOfSilenceOperatorInspection */
if (
    @\is_readable(dirname(__DIR__).'/tmp/installing.json')
        ||
    !\file_exists(dirname(__DIR__).'/config/databases.json')
) {
    include dirname(__DIR__).'/Installer/launch.php';
    exit;
}

/**
 * Load the bare minimum:
 */
require_once \dirname(__DIR__).'/preload.php';

$start = \microtime(true);
if (empty($_POST)) {

    /**
     * Let's get rid of trailing slashes in URLs without POST data
     */
    $sliceAt = \strlen($_SERVER['REQUEST_URI']) - 1;
    if ($sliceAt > 0 && $_SERVER['REQUEST_URI'][$sliceAt] === '/') {
        \Airship\redirect(
            '/' . \trim($_SERVER['REQUEST_URI'], '/')
        );
    }

    /**
     * Let's handle static content caching
     */
    if (\extension_loaded('apcu')) {
        $staticCache = (new MemoryCache())
            ->personalize('staticPage:');
        $cspCache = (new MemoryCache())
            ->personalize('contentSecurityPolicy:');
    } else {
        $staticCache = new FileCache(ROOT . '/tmp/cache/static');
        $cspCache = new FileCache(ROOT . '/tmp/cache/csp_static');
    }
    $port = $_SERVER['HTTP_PORT'] ?? '';
    $lookup = $_SERVER['HTTP_HOST'] . ':' . $port . '/' . $_SERVER['REQUEST_URI'];
    $staticPage = $staticCache->get($lookup);
    if (!empty($staticPage)) {
        if (!\headers_sent()) {
            \header('Content-Type: text/html;charset=UTF-8');
            \header('Content-Language: ' . $state->lang);
            \header('X-XSS-Protection: 1; mode=block');
            \header('X-Frame-Options: SAMEORIGIN'); // Maybe make this configurable down the line?
        }
        $csp =  $cspCache->get($lookup);
        if (!empty($csp)) {
            foreach (\json_decode($csp, true) as $cspHeader) {
                \header($cspHeader);
            }
        }

        echo $staticPage;
        // This is just for benchmarking purposes:
        echo '<!-- Load time: ' . \round(\microtime(true) - $start, 5) . ' s (static page) -->';
        exit;
    }
    unset($staticCache);
}

// Load all of the prerequisites:
require_once ROOT . '/bootstrap.php';

/**
 * Initialize the automatic updater service
 *
 * Normally you would just want a cron job to run continuum.php every hour or so,
 * but this forces it to be run.
 */
$autoUpdater = Gears::get('AutoUpdater', $hail);
if ($autoUpdater->needsUpdate()) {
    $script = ROOT . '/CommandLine/continuum.php';

    /**
     * @security Make sure this is never compromised:
     */
    \shell_exec(
        'php -dphar.readonly=0 ' .
            \escapeshellarg($script) .
        ' >/dev/null 2>&1 &'
    );

    \file_put_contents(
        ROOT.'/tmp/last_update_check.txt',
        time()
    );
}

/**
 * Let's load the latest gear for our autoloader
 */
\define('CABIN_NAME', $active['name']);
\define('CABIN_DIR', ROOT . '/Cabin/' . $active['name']);

// Turn all of this cabins' Landings and Blueprints into gears:
require ROOT . '/cabin_gears.php';

$autoPilot = Gears::get(
    'AutoPilot',
    $active,
    $lens,
    $dbPool
);

if ($autoPilot instanceof AutoPilot) {
    $autoPilot->setActiveCabin(
        $active,
        $state->active_cabin
    );
}

// Load everything else:
require ROOT . '/symlinks.php';
require ROOT . '/motifs.php';
require ROOT . '/security.php';
require ROOT . '/email.php';

$state->autoPilot = $autoPilot;

/**
 * Final step: Let's turn on the autopilot
 */
if (!empty($state->universal['debug'])) {
    try {
        \error_reporting(E_ALL);
        \ini_set('display_errors', 'On');

        $autoPilot->route();
    } catch (\Throwable $e) {
        if (!\headers_sent()) {
            header('Content-Type: text/plain;charset=UTF-8');
        }
        $state->logger->log(
            LogLevel::ERROR,
            $e->getMessage(),
            \Airship\throwableToArray($e)
        );
        echo "DEBUG ERROR: ", \get_class($e), "\n\n",
            $e->getMessage(), "\n\n",
            $e->getCode(), "\n\n",
            $e->getTraceAsString();

        // Show previous throwables as well:
        $n = 1;
        $e = $e->getPrevious();
        // The methods below exist in both \Exception and \Error.
        while ($e instanceof \Exception || $e instanceof \Error) {
            echo "\n", \str_repeat('#', 80), "\n";
            /** @noinspection PhpUndefinedMethodInspection */
            echo "PREVIOUS ERROR (", $n, "): ", \get_class($e), "\n\n",
                $e->getMessage(), "\n\n",
                $e->getCode(), "\n\n",
                $e->getTraceAsString();
            ++$n;
            /** @noinspection PhpUndefinedMethodInspection */
            $e = $e->getPrevious();
        }
    }
    // This is just for benchmarking purposes:
    echo '<!-- Load time: ' . \round(\microtime(true) - $start, 5) . ' s -->';
} else {
    try {
        $autoPilot->route();
    } catch (\Throwable $e) {
        $state->logger->log(
            LogLevel::ERROR,
            $e->getMessage(),
            \Airship\throwableToArray($e)
        );

        \header('HTTP/1.1 500 Internal Server Error');
        echo \file_get_contents(
            __DIR__ . '/../error_pages/uncaught-exception.html'
        );
        exit(1);
    }
}
