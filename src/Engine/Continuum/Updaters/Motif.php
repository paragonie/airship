<?php
declare(strict_types=1);
namespace Airship\Engine\Continuum\Updaters;

use Airship\Alerts\{
    Continuum\CouldNotUpdate,
    Continuum\MotifZipFailed,
    Hail\NoAPIResponse
};
use Airship\Engine\{
    Contract\ContinuumInterface,
    Continuum\AutoUpdater,
    Continuum\Log,
    Continuum\Supplier,
    Hail
};
use Psr\Log\LogLevel;

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
    protected $ext = 'zip';

    /**
     * Motif constructor.
     *
     * @param Hail $hail
     * @param array $manifest
     * @param Supplier|null $supplier
     * @param string $filePath
     * @throws \Error
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
        if ($supplier === null) {
            throw new \Error('Unknown supplier');
        }
        $this->supplier = $supplier;
        $this->filePath = $filePath;
        $this->type = self::TYPE_MOTIF;
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
     * @return void
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
                        'Skipping Motif update',
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
                        self::$continuumLogger->store(
                            LogLevel::ALERT,
                            'Motif update failed -- checksum not registered in Keyggdrasil',
                            $this->getLogContext($updateInfo, $updateFile)
                        );
                    }
                } else {
                    $this->log('Signature check failed for this Motif', LogLevel::ALERT, $debugArgs);
                    self::$continuumLogger->store(
                        LogLevel::ALERT,
                        'Motif update failed -- invalid signature',
                        $this->getLogContext($updateInfo, $updateFile)
                    );
                }
            }
        } catch (NoAPIResponse $ex) {
            // We should log this.
            $this->log(
                'Automatic update failure: NO API Response.',
                LogLevel::CRITICAL,
                \Airship\throwableToArray($ex)
            );
            self::$continuumLogger->store(
                LogLevel::ALERT,
                'Motif update failed -- no API Response',
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
     * Install the new version
     *
     * If we get to this point:
     *
     * 1. We know the signature is signed by the supplier.
     * 2. The hash was checked into Keyggdrasil, which
     *    was independently vouched for by our peers.
     *
     * @param UpdateInfo $info (part of definition but not used here)
     * @param UpdateFile $file
     * @return void
     * @throws CouldNotUpdate
     * @throws MotifZipFailed
     */
    protected function install(UpdateInfo $info, UpdateFile $file)
    {
        if (!$file->hashMatches($info->getChecksum())) {
            throw new CouldNotUpdate(
                \__('Checksum mismatched')
            );
        }
        // Let's open the update package:
        $path = $file->getPath();
        $zip = new \ZipArchive();
        $res = $zip->open($path);
        if ($res !== true) {
            throw new MotifZipFailed(
                \__(
                    "ZIP Error: %s", "default",
                    $res
                )
            );
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
        self::$continuumLogger->store(
            LogLevel::INFO,
            'Motif update installed',
            $this->getLogContext($info, $file)
        );
    }

    /**
     * Store cabin association
     *
     * @param string $supplier
     * @param string $name
     * @return self
     */
    public function setCabin(string $supplier, string $name): self
    {
        $this->cabin = [$supplier, $name];
        return $this;
    }
}
