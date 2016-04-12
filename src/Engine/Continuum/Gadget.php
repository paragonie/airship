<?php
declare(strict_types=1);
namespace Airship\Engine\Continuum;

use \Airship\Alerts\Hail\{
    NoAPIResponse,
    PeerVerificationFailure
};
use \Airship\Engine\Contract\ContinuumInterface;
use \Airship\Engine\Hail;
use \ParagonIE\ConstantTime\Base64UrlSafe;
use \Psr\Log\LogLevel;

class Gadget extends AutoUpdater implements ContinuumInterface
{
    private $hail;
    private $cabin;
    private $name;
    private $supplier;
    private $filePath;
    private $manifest;

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
     * 4. If all is well, run the update script.
     */
    public function autoUpdate()
    {
        try {
            $updateInfoArray = $this->updateCheck(
                $this->supplier->getName(),
                $this->name,
                $this->manifest['version']
            );
            foreach ($updateInfoArray as $updateInfo) {
                $res = $updateInfo->getResponse();
                if ($res['status'] !== 'error') {
                    $updateFile = $this->downloadUpdateFile($updateInfo);
                    /**
                     * Don't proceed unless we've verified the signatures
                     */
                    if ($this->verifyUpdateSignature($updateInfo, $updateFile)) {
                        $this->install($updateInfo, $updateFile);
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
     * @throws PeerVerificationFailure
     */
    protected function install(UpdateInfo $info, UpdateFile $file)
    {
        /**
         * If peer verification is implemented, we'll block updates here.
         */
        if (!$this->verifyChecksumWithPeers($info, $file)) {
            throw new PeerVerificationFailure(
                \trk(
                    'errors.hail.peer_checksum_failed',
                    $info->getVersion(),
                    'Gadget: ' . $this->name
                )
            );
        }

        \rename($this->filePath, $this->filePath.'.backup');
        \rename($file->getPath(), $this->filePath);


        // Let's open the update package:
        $newGadget = new \Phar(
            $this->filePath,
            \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::KEY_AS_FILENAME
        );
        $newGadget->setAlias($this->pharAlias);
        $metadata = \json_decode($newGadget->getMetadata(), true);

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
