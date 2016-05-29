<?php
declare(strict_types=1);
namespace Airship\Engine\Continuum\Installers;

use \Airship\Engine\Continuum\{
    Installer as BaseInstaller,
    Sandbox
};
use \ParagonIE\ConstantTime\Base64UrlSafe;
use \Psr\Log\LogLevel;

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

    /**
     * Update this cabin's gadgets.json file.
     *
     * @param string $cabin
     * @return bool
     */
    public function addToCabin(string $cabin): bool
    {
        $configFile = ROOT . '/Cabin/' . $cabin . '/config/gadgets.json';
        $gadgets = \Airship\loadJSON($configFile);
        $gadgets[] = [
            'active' =>
                false,
            'supplier' =>
                $this->supplier->getName(),
            'name' =>
                $this->package
        ];
        return \file_put_contents(
            $configFile,
            \json_encode($gadgets, JSON_PRETTY_PRINT)
        ) !== false;
    }

    /**
     * Get the metadata stored in the PHP archive.
     *
     * @param InstallFile $fileInfo
     * @return array
     */
    protected function getMetadata(InstallFile $fileInfo): array
    {
        $alias = Base64UrlSafe::encode(\random_bytes(33)) . '.phar';
        $phar = new \Phar(
            $fileInfo->getPath(),
            \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::KEY_AS_FILENAME
        );
        $phar->setAlias($alias);
        $metadata = $phar->getMetadata();
        unset($phar);
        return $metadata;
    }

    /**
     * Gadget install process.
     *
     * 1. Move .phar to the appropriate location.
     * 2. If this gadget is for a particular cabin, add it to that cabin's
     *    gadgets.json file.
     * 3. Run the update triggers (install hooks and incremental upgrades).
     * 4. Clear the cache files.
     *
     * @param InstallFile $fileInfo
     * @return bool
     */
    public function install(InstallFile $fileInfo): bool
    {
        $supplier = $this->supplier->getName();
        $fileName = $supplier . '.' . $this->package . '.phar';
        $metadata = $this->getMetadata($fileInfo);

        // Move .phar file to its destination.
        if (!empty($metadata['cabin'])) {
            // Cabin-specific gadget
            $cabin = ROOT . '/Cabin/' . $metadata['cabin'] . '/Gadgets';
            if (!\is_dir($cabin)) {
                $this->log(
                    'Could not install; cabin "' . $metadata['cabin'] . '" is not installed.',
                    LogLevel::ERROR
                );
                return false;
            }
            $filePath = $cabin . '/' . $supplier . '/' . $fileName;
            if (!\is_dir($cabin . '/' . $supplier)) {
                \mkdir($cabin . '/' . $supplier, 0775);
            }
        } else {
            // Universal gadget. (Probably affects the Engine.)
            $filePath = ROOT . '/Gadgets/' . $supplier . '/' . $fileName;
            if (!\is_dir(ROOT . '/Gadgets/' . $supplier)) {
                \mkdir(ROOT . '/Gadgets/' . $supplier, 0775);
            }
        }
        \rename($fileInfo->getPath(), $filePath);

        // If cabin-specific, add to the cabin's gadget.json
        if ($metadata['cabin']) {
            $this->addToCabin($metadata['cabin']);
        }

        // Run the update hooks:
        $alias = 'gadget.' . $fileName;
        $phar = new \Phar(
            $filePath,
            \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::KEY_AS_FILENAME
        );
        $phar->setAlias($alias);

        // Run the update trigger.
        if (\file_exists('phar://' . $alias . '/update_trigger.php')) {
            Sandbox::safeRequire('phar://' . $alias . '/update_trigger.php');
        }

        // Finally, clear the cache files:
        return $this->clearCache();
    }
}
