<?php
declare(strict_types=1);

use Airship\Alerts\Router\ControllerComplete;
use Airship\Engine\{
    AutoPilot,
    Database,
    Gears,
    Hail,
    View,
    State
};
use Airship\Engine\Networking\HTTP\{
    ServerRequest
};
use Psr\Log\LogLevel;

/**
 * @global array $active
 * @global Database[] $dbPool
 * @global State $state
 * @global View $lens
 * @global AutoPilot $autopilot
 */

// Are we still installing?
/** @noinspection PhpUsageOfSilenceOperatorInspection */
if (
    @\is_readable(dirname(__DIR__) . '/tmp/installing.json')
        ||
    !\file_exists(dirname(__DIR__) . '/config/databases.json')
) {
    include dirname(__DIR__) . '/Installer/launch.php';
    exit;
}

/**
 * Load the bare minimum:
 */
require_once \dirname(__DIR__) . '/preload.php';

$start = \microtime(true);
require_once ROOT . '/static-cache.php';

// Load all of the prerequisites:
require_once ROOT . '/bootstrap.php';

/**
 * Initialize the automatic updater service
 *
 * Normally you would just want a cron job to run continuum.php every hour or so,
 * but this forces it to be run.
 *
 * @global Hail $hail
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
        ROOT . '/tmp/last_update_check.txt',
        time()
    );
}

require_once ROOT . '/boot_final.php';

/**
 * Final step: Let's turn on the autopilot
 */
if (!empty($state->universal['debug'])) {
    try {
        \error_reporting(E_ALL);
        \ini_set('display_errors', 'On');

        $autoPilot->serveResponse(
            $autoPilot->route(ServerRequest::fromGlobals())
        );
    } catch (ControllerComplete $ex) {
            $autoPilot->serveResponse();
    } catch (\Throwable $e) {
        if (!\headers_sent()) {
            foreach (\Airship\get_standard_headers('text/plain;charset=UTF-8') as $left => $right) {
                \header($left . ': ' . $right);
            }
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
            // The methods below exist in both \Exception and \Error.
            while ($e = $e->getPrevious()) {
                if ($e instanceof \Exception) {
                    echo "\n", \str_repeat('#', 80), "\n";
                    echo "PREVIOUS EXCEPTION (", $n, "): ", \get_class($e), "\n\n",
                    $e->getMessage(), "\n\n",
                    'File: ', $e->getFile(), "\n",
                    'Line: ', $e->getLine(), "\n",
                    'Code: ', $e->getCode(), "\n\n",
                    $e->getTraceAsString();
                    ++$n;
                    if (!$e) {
                        exit(255);
                    }
                } elseif ($e instanceof \Error) {
                    echo "\n", \str_repeat('#', 80), "\n";
                    echo "PREVIOUS ERROR (", $n, "): ", \get_class($e), "\n\n",
                    $e->getMessage(), "\n\n",
                    'File: ', $e->getFile(), "\n",
                    'Line: ', $e->getLine(), "\n",
                    'Code: ', $e->getCode(), "\n\n",
                    $e->getTraceAsString();
                    ++$n;
                    if (!$e) {
                        exit(255);
                    }
                } else {
                    break;
                }
            }
            exit(255);
    }
    // This is just for benchmarking purposes:
    echo '<!-- Load time: ' . \round(\microtime(true) - $start, 5) . ' s -->';
} else {
    try {
        $autoPilot->serveResponse(
            $autoPilot->route(ServerRequest::fromGlobals())
        );
    } catch (ControllerComplete $ex) {
        $autoPilot->serveResponse();
    } catch (\Throwable $e) {
        $state->logger->log(
            LogLevel::ERROR,
            $e->getMessage(),
            \Airship\throwableToArray($e)
        );

        \http_response_code(500);
        echo \file_get_contents(
            ROOT . '/error_pages/uncaught-exception.html'
        );
        exit(1);
    }
}
