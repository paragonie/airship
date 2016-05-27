<?php
declare(strict_types=1);
namespace Airship\Engine\Continuum;

use \Airship\Alerts\Continuum\{
    CouldNotUpdate,
    MotifZipFailed
};
use \Airship\Alerts\Hail\NoAPIResponse;
use \Airship\Engine\Contract\ContinuumInterface;
use \Airship\Engine\Hail;
use \Psr\Log\LogLevel;

/**
 * Class Motif
 *
 * This updates a Motif.
 *
 * @package Airship\Engine\Continuum
 */
class Motif extends AutoUpdater implements ContinuumInterface
{
    protected $hail;
    protected $cabin;
    protected $name;
    protected $supplier;
    protected $filePath;
    protected $manifest;

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
        $this->type = self::TYPE_MOTIF;
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
                $updateFile = $this->downloadUpdateFile($updateInfo);
                /**
                 * Don't proceed unless we've verified the signatures
                 */
                if ($this->verifyUpdateSignature($updateInfo, $updateFile)) {
                    if ($this->checkKeyggdrasil($updateInfo, $updateFile)) {
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
     * Install the new version
     *
     * @param UpdateInfo $info (part of definition but not used here)
     * @param UpdateFile $file
     * @throws CouldNotUpdate
     * @throws MotifZipFailed
     */
    protected function install(UpdateInfo $info, UpdateFile $file)
    {
        if (!$file->hashMatches($info->getChecksum())) {
            throw new CouldNotUpdate(
                'Checksum mismatched'
            );
        }
        // Let's open the update package:
        $path = $file->getPath();
        $zip = new \ZipArchive();
        $res = $zip->open($path);
        if ($res !== true) {
            throw new MotifZipFailed($res);
        }
        $dir = \implode(
            DIRECTORY_SEPARATOR,
            [
                ROOT,
                'Motifs',
                $this->supplier->getName(),
                $this->name
            ]
        );

        // Extract the new files to the current directory
        if (!$zip->extractTo($dir)) {
            throw new CouldNotUpdate();
        }
    }

    /**
     * @param string $supplier
     * @param string $name
     * @return Motif
     */
    public function setCabin(string $supplier, string $name): self
    {
        $this->cabin = [$supplier, $name];
        return $this;
    }
}
