<?php
declare(strict_types=1);
namespace Airship\Engine\Continuum\Installers;

use \Airship\Engine\Continuum\Installer as BaseInstaller;

/**
 * Class Cabin
 *
 * This allows a new Cabin to be installed.
 *
 * @package Airship\Engine\Continuum\Installer
 */
class Cabin extends BaseInstaller
{
    protected $type = 'Cabin';
    protected $ext = 'phar';

    public function install(): bool
    {

    }

    public function clearCache(): bool
    {

    }
}
