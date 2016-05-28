<?php
declare(strict_types=1);
namespace Airship\Engine\Continuum\Installers;

use \Airship\Engine\Continuum\Installer as BaseInstaller;

/**
 * Class Motif
 *
 * This allows a new Motif to be installed
 *
 * @package Airship\Engine\Continuum\Installer
 */
class Motif extends BaseInstaller
{
    protected $type = 'Motif';
    protected $ext = 'zip';

    public function install(InstallFile $fileInfo): bool
    {

    }

    public function clearCache(): bool
    {

    }
}
