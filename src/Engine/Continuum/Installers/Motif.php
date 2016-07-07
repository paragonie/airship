<?php
declare(strict_types=1);
namespace Airship\Engine\Continuum\Installers;

use Airship\Engine\Continuum\Installer as BaseInstaller;
use Psr\Log\LogLevel;

/**
 * Class Motif
 *
 * This allows a new Motif to be installed
 *
 * @package Airship\Engine\Continuum\Installer
 */
class Motif extends BaseInstaller
{
    /**
     * @var string
     */
    protected $type = 'Motif';

    /**
     * @var string
     */
    protected $ext = 'zip';

    /**
     * Add the new motif as an option to the Cabin
     *
     * @param string $cabin
     * @return bool
     */
    protected function addMotifToCabin(string $cabin): bool
    {
        if (!\is_dir(ROOT . '/Cabin/' . $cabin)) {
            return false;
        }
        $supplier = $this->supplier->getName();
        $name = $this->package;

        $configFile = ROOT . '/Cabin/' . $cabin . '/config/motifs.json';
        $config = \Airship\loadJSON($configFile);

        $i = 1;
        while (isset($config[$name])) {
            $name = $this->package . '-' . ++$i;
        }

        $config[$name] = [
            'enabled' =>
                true,
            'supplier' =>
                $supplier,
            'name' =>
                $this->package,
            'path' =>
                $supplier . '/' . $this->package
        ];

        if (!$this->createSymlinks($cabin, $config, $name)) {
            // This isn't essential.
            $this->log(
                'Could not create symlinks for Cabin "' . $cabin . '".',
                LogLevel::WARNING
            );
        }

        return \Airship\saveJSON($configFile, $config);
    }

    /**
     * Setup the initial symbolic links
     *
     * @param string $cabin
     * @param array $config
     * @param string $index
     * @return bool
     */
    protected function createSymlinks(
        string $cabin,
        array $config,
        string $index
    ): bool {
        $res = \symlink(
            // to:
            ROOT . '/Motifs/' . $config[$index]['path'] . '/public',
            // from:
            ROOT . '/Cabin/' . $cabin . '/public/motif/ ' . $index
        );

        $res = $res && \symlink(
            // to:
            ROOT . '/Motifs/' . $config[$index]['path'] . '/lens',
            // from:
            ROOT . '/Cabin/' . $cabin . '/Lens/motif/ ' . $index
        );

        return $res;
    }

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
        $path = $fileInfo->getPath();
        $zip = new \ZipArchive();
        $res = $zip->open($path);
        if ($res !== true) {
            $this->log(
                'Could not open the ZipArchive.',
                LogLevel::ERROR
            );
            return false;
        }

        // Extraction destination directory
        $dir = \implode(
            DIRECTORY_SEPARATOR,
            [
                ROOT,
                'Motifs',
                $this->supplier->getName(),
                $this->package
            ]
        );
        if (!\is_dir($dir)) {
            \mkdir($dir, 0775, true);
        }

        // Grab metadata
        $metadata = \Airship\parseJSON(
            $zip->getArchiveComment(\ZipArchive::FL_UNCHANGED),
            true
        );
        if (isset($metadata['cabin'])) {
            $cabin = $this->expandCabinName($metadata['cabin']);
            if (!\is_dir(ROOT . '/Cabin/' . $cabin)) {
                $this->log(
                    'Could not install; cabin "' . $cabin . '" is not installed.',
                    LogLevel::ERROR
                );
                return false;
            }
        } else {
            $cabin = null;
        }

        // Extract the new files to the current directory
        if (!$zip->extractTo($dir)) {
            $this->log(
                'Could not extract Motif to its destination.',
                LogLevel::ERROR
            );
            return false;
        }

        // Add to the relevant motifs.json files
        if ($cabin) {
            $this->addMotifToCabin($cabin);
        } else {
            foreach (\glob(ROOT . '/Cabin/') as $cabin) {
                if (\is_dir($cabin)) {
                    $this->addMotifToCabin(
                        \Airship\path_to_filename($cabin)
                    );
                }
            }
        }

        self::$continuumLogger->store(
            LogLevel::INFO,
            'Motif install successful',
            $this->getLogContext($fileInfo)
        );

        // Finally, nuke the cache:
        return $this->clearCache();
    }
}
