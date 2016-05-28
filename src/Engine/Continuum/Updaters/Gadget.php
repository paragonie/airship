<?php
declare(strict_types=1);
namespace Airship\Engine\Continuum\Updaters;

use \Airship\Alerts\Continuum\CouldNotUpdate;
use \Airship\Alerts\Hail\NoAPIResponse;
use Airship\Engine\{
    Contract\ContinuumInterface,
    Continuum\AutoUpdater,
    Continuum\Sandbox,
    Continuum\Supplier,
    Hail
};
use \ParagonIE\ConstantTime\Base64UrlSafe;
use \Psr\Log\LogLevel;

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
    protected $name = '';

    /**
     * @var string
     */
    protected $filePath = '';

    /**
     * @var array
     */
    protected $manifest = [];

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
            $updateInfoArray = $this->updateCheck(
                $this->supplier->getName(),
                $this->name,
                $this->manifest['version']
            );
            foreach ($updateInfoArray as $updateInfo) {
                $updateFile = $this->downloadUpdateFile($updateInfo);
                $this->log('Downloaded update file', LogLevel::DEBUG, $debugArgs);
                if ($this->bypassSecurityAndJustInstall) {
                    $this->log('Gadget update verification bypassed', LogLevel::ALERT, $debugArgs);
                    $this->install($updateInfo, $updateFile);
                } else {
                    /**
                     * Don't proceed unless we've verified the signatures
                     */
                    if ($this->verifyUpdateSignature($updateInfo, $updateFile)) {
                        if ($this->checkKeyggdrasil($updateInfo, $updateFile)) {
                            $this->install($updateInfo, $updateFile);
                        } else {
                            $this->log('Keyggdrasil check failed for this Gadget', LogLevel::ERROR, $debugArgs);
                        }
                    }
                }
            }
        } catch (NoAPIResponse $ex) {
            // We should log this.
            $this->log(
                'Automatic update failure: NO API Response.',
                LogLevel::CRITICAL,
                \Airship\throwableToArray($ex)
            );
        }
    }

    /**
     * We just need to replace the Phar
     *
     * @param UpdateInfo $info
     * @param UpdateFile $file
     * @throws CouldNotUpdate
     */
    protected function install(UpdateInfo $info, UpdateFile $file)
    {
        if (!$file->hashMatches($info->getChecksum())) {
            throw new CouldNotUpdate(
                'Checksum mismatched'
            );
        }
        \rename($this->filePath, $this->filePath.'.backup');
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

        $oldAlias = Base64UrlSafe::encode(\random_bytes(48)) . '.phar';
        $oldGadget = new \Phar(
            $this->filePath,
            \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::KEY_AS_FILENAME,
            $oldAlias
        );
        $metadata = \json_decode($oldGadget->getMetadata(), true);
        unset($oldGadget);
        unset($oldAlias);

        // Let's open the update package:
        $newGadget = new \Phar(
            $this->filePath,
            \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::KEY_AS_FILENAME,
            $this->pharAlias
        );
        $newGadget->setAlias($this->pharAlias);

        // We need to do this while we're replacing files.
        $this->bringSiteDown();

        Sandbox::safeRequire(
            'phar://' . $this->pharAlias . '/update_trigger.php',
            $metadata
        );

        // Free up the updater alias
        $garbageAlias = Base64UrlSafe::encode(\random_bytes(48)) . '.phar';
        $newGadget->setAlias($garbageAlias);
        unset($newGadget);

        // Now bring it back up.
        $this->bringSiteBackUp();
    }

    /**
     * @param string $supplier
     * @param string $name
     * @return Gadget
     */
    public function setCabin(string $supplier, string $name): self
    {
        $this->cabin = [$supplier, $name];
        return $this;
    }
}
