<?php
declare(strict_types=1);
namespace Airship\Engine\Continuum\Updaters;

use Airship\Alerts\{
    Continuum\CouldNotUpdate,
    Hail\NoAPIResponse
};
use Airship\Engine\{
    Contract\ContinuumInterface,
    Continuum\AutoUpdater,
    Continuum\Sandbox,
    Continuum\Log,
    Continuum\Supplier,
    Hail
};
use ParagonIE\ConstantTime\Base64UrlSafe;
use Psr\Log\LogLevel;

/**
 * Class Gadget
 *
 * This updates a Gadget.
 *
 * @package Airship\Engine\Continuum
 */
class Gadget extends AutoUpdater implements ContinuumInterface
{
    /**
     * @var array
     */
    protected $cabin = [];

    /**
     * @var string
     */
    protected $ext = 'phar';

    /**
     * Gadget constructor.
     *
     * @param Hail $hail
     * @param array $manifest
     * @param Supplier|null $supplier
     * @param string $filePath
     */
    public function __construct(
        Hail $hail,
        array $manifest = [],
        Supplier $supplier = null,
        string $filePath = ''
    ) {
        $this->hail = $hail;
        $this->name = $manifest['name'];
        $this->manifest = $manifest;
        $this->supplier = $supplier;
        $this->filePath = $filePath;
        $this->type = self::TYPE_GADGET;
        if (!self::$continuumLogger) {
            self::$continuumLogger = new Log();
        }
    }

    /**
     * Process automatic updates:
     *
     * 1. Check if a new update is available.
     * 2. Download the upload file, store in a temporary file.
     * 3. Verify the signature (via Halite).
     * 4. Verify the update is recorded in Keyggdrasil.
     * 5. If all is well, run the update script.
     */
    public function autoUpdate()
    {
        $debugArgs = [
            'supplier' =>
                $this->supplier->getName(),
            'name' =>
                $this->name
        ];
        try {
            /**
             * @var UpdateInfo[]
             */
            $updateInfoArray = $this->updateCheck(
                $this->supplier->getName(),
                $this->name,
                $this->manifest['version']
            );
            foreach ($updateInfoArray as $updateInfo) {
                if (!$this->checkVersionSettings($updateInfo, $this->manifest['version'])) {
                    $this->log(
                        'Skipping Gadget update',
                        LogLevel::INFO,
                        [
                            'info' => $updateInfo->getResponse(),
                            'new_version' => $updateInfo->getVersion(),
                            'current_version' => $this->manifest['version']
                        ]
                    );
                    continue;
                }
                /**
                 * @var UpdateFile
                 */
                $updateFile = $this->downloadUpdateFile($updateInfo);
                $this->log('Downloaded update file', LogLevel::DEBUG, $debugArgs);

                if ($this->bypassSecurityAndJustInstall) {
                    $this->log('Gadget update verification bypassed', LogLevel::ALERT, $debugArgs);
                    $this->install($updateInfo, $updateFile);
                    return;
                }

                /**
                 * Don't proceed unless we've verified the signatures
                 */
                if ($this->verifyUpdateSignature($updateInfo, $updateFile)) {
                    if ($this->checkKeyggdrasil($updateInfo, $updateFile)) {
                        $this->install($updateInfo, $updateFile);
                    } else {
                        $this->log('Keyggdrasil check failed for this Gadget', LogLevel::ALERT, $debugArgs);
                        self::$continuumLogger->store(
                            LogLevel::ALERT,
                            'Gadget update failed -- checksum not registered in Keyggdrasil',
                            $this->getLogContext($updateInfo, $updateFile)
                        );
                    }
                } else {
                    $this->log('Signature check failed for this Gadget', LogLevel::ALERT, $debugArgs);
                    self::$continuumLogger->store(
                        LogLevel::ALERT,
                        'Gadget update failed -- invalid signature',
                        $this->getLogContext($updateInfo, $updateFile)
                    );
                }
            }
        } catch (NoAPIResponse $ex) {
            // We should log this.
            $this->log(
                'Automatic update failure: NO API Response.',
                LogLevel::ERROR,
                \Airship\throwableToArray($ex)
            );
            self::$continuumLogger->store(
                LogLevel::ALERT,
                'Gadget update failed -- no API Response',
                [
                    'action' => 'UPDATE',
                    'name' => $this->name,
                    'supplier' => $this->supplier->getName(),
                    'type' => $this->type
                ]
            );
        }
    }

    /**
     * We just need to replace the Phar
     *
     * If we get to this point:
     *
     * 1. We know the signature is signed by the supplier.
     * 2. The hash was checked into Keyggdrasil, which
     *    was independently vouched for by our peers.
     *
     * @param UpdateInfo $info
     * @param UpdateFile $file
     * @throws CouldNotUpdate
     */
    protected function install(UpdateInfo $info, UpdateFile $file)
    {
        if (!$file->hashMatches($info->getChecksum())) {
            throw new CouldNotUpdate(
                \__('Checksum mismatched')
            );
        }

        // Create a backup of the old Gadget:
        \rename($this->filePath, $this->filePath . '.backup');
        \rename($file->getPath(), $this->filePath);

        $this->log(
            'Begin install process',
            LogLevel::DEBUG,
            [
                'path' => $file->getPath(),
                'hash' => $file->getHash(),
                'version' => $file->getVersion(),
                'size' => $file->getSize()
            ]
        );

        // Get metadata from the old version of this Gadget:
        $oldAlias = Base64UrlSafe::encode(\random_bytes(48)) . '.phar';
        $oldGadget = new \Phar(
            $this->filePath,
            \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::KEY_AS_FILENAME
        );
        $oldGadget->setAlias($oldAlias);
        $oldMetadata = $oldGadget->getMetadata();
        unset($oldGadget);
        unset($oldAlias);

        // Let's open the update package:
        $newGadget = new \Phar(
            $this->filePath,
            \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::KEY_AS_FILENAME,
            $this->pharAlias
        );
        $newGadget->setAlias($this->pharAlias);
        $metaData = $newGadget->getMetadata();

        // We need to do this while we're replacing files.
        $this->bringSiteDown();

        Sandbox::safeRequire(
            'phar://' . $this->pharAlias . '/update_trigger.php',
            $oldMetadata
        );

        // Free up the updater alias
        $garbageAlias = Base64UrlSafe::encode(\random_bytes(48)) . '.phar';
        $newGadget->setAlias($garbageAlias);
        unset($newGadget);

        // Now bring it back up.
        $this->bringSiteBackUp();

        // Make sure we update the version info. in the DB cache:
        $this->updateDBRecord('Gadget', $info);
        if ($metaData) {
            $this->updateJSON($info, $metaData);
        }
        self::$continuumLogger->store(
            LogLevel::INFO,
            'Gadget update installed',
            $this->getLogContext($info, $file)
        );
    }

    /**
     * Store cabin association
     *
     * @param string $supplier
     * @param string $name
     * @return Gadget
     */
    public function setCabin(string $supplier, string $name): self
    {
        $this->cabin = [$supplier, $name];
        return $this;
    }

    /**
     * Update the version identifier stored in the gadgets.json file
     *
     * @param UpdateInfo $info
     * @param array $metaData
     */
    public function updateJSON(UpdateInfo $info, array $metaData = [])
    {
        if (!empty($metaData['cabin'])) {
            $gadgetConfigFile = ROOT .
                '/Cabin/' .
                $metaData['cabin'] .
                '/config/gadgets.json';
        } else {
            $gadgetConfigFile = ROOT . '/config/gadgets.json';
        }
        $gadgetConfig = \Airship\loadJSON($gadgetConfigFile);
        foreach ($gadgetConfig as $i => $gadget) {
            if ($gadget['supplier'] === $info->getSupplierName()) {
                if ($gadget['name'] === $info->getPackageName()) {
                    $gadgetConfig[$i]['version'] = $info->getVersion();
                    break;
                }
            }
        }
        \Airship\saveJSON($gadgetConfigFile, $gadgetConfig);
    }
}
