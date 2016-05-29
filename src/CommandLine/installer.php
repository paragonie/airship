<?php
declare(strict_types=1);

use \Airship\Engine\Continuum\Installers\{
    Cabin,
    Gadget,
    Motif
};
use \Airship\Engine\State;

require_once \dirname(__DIR__).'/bootstrap.php';

/**
 * Show the usage
 */
function usage()
{
    echo 'Command Line Extension Installer - Usage:', "\n";
    echo 'To download from the Internet:', "\n\t";
    echo 'install.sh [type] [supplier]/[package]', "\n";
    echo 'To bypass all security:', "\n\t";
    echo 'install.sh --bypass-security [type] [supplier]/[package]', "\n";
    echo 'To install from a local file:', "\n\t";
    echo 'install.sh [type] [supplier]/[package] [source file]', "\n";
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
$source = \array_shift($args) ?? null;

list($supplier, $package) = \explode('/', $what);
if (empty($supplier) || empty($package)) {
    usage();
}

/* ========================================================================= */
/* #                               Installing                              # */
/* ========================================================================= */

$state = State::instance();
$installerArgs = [
    $state->hail,
    $supplier,
    $package
];
switch (\strtolower($type)) {
    case 'cabin':
        $installer = new Cabin(...$installerArgs);
        break;
    case 'gadget':
        $installer = new Gadget(...$installerArgs);
        break;
    case 'motif':
        $installer = new Motif(...$installerArgs);
        break;
}

// Local source file:
if ($source) {
    $version = $this->prompt("What version should we expect? ");
    $installer->useLocalInstallFile($source, $version);
}

// Dangerous:
if ($bypassSecurity) {
    $installer->bypassSecurityAndJustInstall(true);
}

// Now let's run the easy-install process:
if ($installer->easyInstall()) {
    echo 'Success.', "\n";
    exit(0);
} else {
    echo 'Install unsuccessful. Check the logs for more information.', "\n";
    exit(255);
}