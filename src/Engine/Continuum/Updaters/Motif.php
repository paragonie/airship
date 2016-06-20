<?php
declare(strict_types=1);
namespace Airship\Engine\Continuum\Updaters;

use \Airship\Alerts\Continuum\{
    CouldNotUpdate,
    MotifZipFailed
};
use \Airship\Alerts\Hail\NoAPIResponse;
use \Airship\Engine\{
    Contract\ContinuumInterface,
    Continuum\AutoUpdater,
    Continuum\Supplier,
    Hail
};
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
     * @var string
     */
    protected $ext = 'zip';

    /**
     * Motif constructor.
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
        $this->type = self::TYPE_MOTIF;
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
                /**
                 * @var UpdateFile
                 */
                $updateFile = $this->downloadUpdateFile($updateInfo);
                $this->log('Downloaded Motif update file', LogLevel::DEBUG, $debugArgs);

                if ($this->bypassSecurityAndJustInstall) {
                    $this->log('Motif update verification bypassed', LogLevel::ALERT, $debugArgs);
                    $this->install($updateInfo, $updateFile);
                    return;
                }

                /**
                 * Don't proceed unless we've verified the signatures
                 * and the relevant entries in Keyggdrasil
                 */
                if ($this->verifyUpdateSignature($updateInfo, $updateFile)) {
                    if ($this->checkKeyggdrasil($updateInfo, $updateFile)) {
                        $this->install($updateInfo, $updateFile);
                    } else {
                        $this->log('Keyggdrasil check failed for this Motif', LogLevel::ERROR, $debugArgs);
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

        // Make sure we update the version info. in the DB cache:
        $this->updateDBRecord('Motif', $info);
    }

    /**
     * Store cabin association
     *
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
