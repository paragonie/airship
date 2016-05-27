<?php
declare(strict_types=1);
namespace Airship\Engine\Continuum\Installers;

use \Airship\Engine\Continuum\Installer as BaseInstaller;

/**
 * Class Gadget
 *
 * This allows a new Gadget to be installed
 *
 * @package Airship\Engine\Continuum\Installer
 */
class Gadget extends BaseInstaller
{
    protected $type = 'Gadget';
    protected $ext = 'phar';

    public function install(): bool
    {

    }

    public function clearCache(): bool
    {

    }
}
