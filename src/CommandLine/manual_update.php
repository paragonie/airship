<?php
declare(strict_types=1);

use Airship\Engine\Continuum\Updaters\{
    Airship,
    Cabin,
    Gadget,
    Motif
};
use Airship\Engine\State;

require_once \dirname(__DIR__).'/bootstrap.php';

/**
 * Show the usage
 */
function usage()
{
    echo 'Command Line Extension Updater - Usage:', "\n";
    echo 'To download from the Internet:', "\n\t";
    echo 'update.sh [type] [supplier]/[package] [version identifier]', "\n";
    echo 'To update from a local file:', "\n\t";
    echo 'update.sh [type] [supplier]/[package] [version identifier] [source file]', "\n";
    echo 'To bypass all security checks:', "\n\t";
    echo 'update.sh --bypass-security [type] [supplier]/[package] [version identifier] [source file]', "\n";
    exit(0);
}
/**
 * Request a value.
 * @param string $text
 * @return string
 */
function prompt(string $text = '')
{
    static $fp = null;
    if ($fp === null) {
        $fp = \fopen('php://stdin', 'r');
    }
    echo $text;
    return \substr(\fgets($fp), 0, -1);
}

/* ========================================================================= */
/* #                            Argument parsing                           # */
/* ========================================================================= */

$args = \array_slice($argv, 1);
$type = \array_shift($args) ?? usage();
if ($type === '--bypass-security') {
    $bypassSecurity = true;
    $type = \array_shift($args) ?? usage();
} else {
    $bypassSecurity = false;
}

$what = \array_shift($args) ?? usage();
$version = \array_shift($args) ?? null;
$source = \array_shift($args) ?? null;

list($supplier, $package) = \explode('/', $what);
if (empty($supplier) || empty($package) || empty($version)) {
    usage();
}

/* ========================================================================= */
/* #                               Installing                              # */
/* ========================================================================= */

$state = State::instance();
$updaterArgs = [
    $state->hail,
    $supplier,
    $package
];
switch (\strtolower($type)) {
    case 'airship':
        $updater = new Airship(...$updaterArgs);
        break;
    case 'cabin':
        $updater = new Cabin(...$updaterArgs);
        break;
    case 'gadget':
        $updater = new Gadget(...$updaterArgs);
        break;
    case 'motif':
        $updater = new Motif(...$updaterArgs);
        break;
}

if ($source) {
    $updater->useLocalUpdateFile($source, $version);
}
if ($bypassSecurity) {
    $updater->bypassSecurityAndJustInstall(true);
}
if ($updater->manualUpdate($version)) {
    echo 'Success.', "\n";
    exit(0);
} else {
    echo 'Install unsuccessful. Check the logs for more information.', "\n";
}
