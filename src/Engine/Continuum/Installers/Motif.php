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

    /**
     * Motif install process.
     *
     * 1. Extract files to the appropriate directory.
     * 2. If this is a cabin-specific motif, update motifs.json.
     *    Otherwise, it's a global Motif. Enable for all cabins.
     * 3. Create symbolic links.
     * 4. Clear cache files.
     *
     * @param InstallFile $fileInfo
     * @return bool
     */
    public function install(InstallFile $fileInfo): bool
    {

    }
}
