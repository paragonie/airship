<?php
declare(strict_types=1);
namespace Airship\Engine\Continuum;

use \Airship\Alerts\Continuum\CouldNotUpdate;
use \Airship\Alerts\Hail\NoAPIResponse;
use \Airship\Engine\{
    Contract\ContinuumInterface,
    Hail,
    State
};
use \ParagonIE\ConstantTime\Base64UrlSafe;
use \Psr\Log\LogLevel;

/**
 * Class Airship
 *
 * This is used to self-update the Airship
 *
 * @package Airship\Engine\Continuum
 */
class Airship extends AutoUpdater implements ContinuumInterface
{
    protected $pharAlias = 'airship-update.phar';
    protected $name = 'airship'; // Package name
    
    public function __construct(Hail $hail, Supplier $sup)
    {
        $this->supplier = $sup;
        $this->hail = $hail;
        $this->type = self::TYPE_ENGINE;
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
        $state = State::instance();
        try {
            $updates = $this->updateCheck(
                $state->universal['airship']['trusted-supplier'],
                $this->name,
                \AIRSHIP_VERSION,
                'airship_version'
            );
            foreach ($updates as $updateInfo) {
                if (!$this->checkVersionSettings($updateInfo, \AIRSHIP_VERSION)) {
                    $this->log(
                        'Skipping update',
                        LogLevel::INFO,
                        [
                            'info' => $updateInfo->getResponse(),
                            'new_version' => $updateInfo->getVersion(),
                            'current_version' => \AIRSHIP_VERSION
                        ]
                    );
                    continue;
                }
                $updateFile = $this->downloadUpdateFile(
                    $updateInfo,
                    'airship_download'
                );
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
     * Let's install the automatic update.
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
        // Let's open the update package:
        $path = $file->getPath();
        $updater = new \Phar(
            $path,
            \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::KEY_AS_FILENAME
        );
        $updater->setAlias($this->pharAlias);
        $metadata = \json_decode($updater->getMetadata(), true);

        // We need to do this while we're replacing files.
        $this->bringSiteDown();

        if (isset($metadata['files'])) {
            foreach ($metadata['files'] as $fileName) {
                $this->replaceFile($fileName);
            }
        }
        if (isset($metadata['autoRun'])) {
            foreach ($metadata['autoRun'] as $autoRun) {
                $this->autoRunScript($autoRun);
            }
        }

        // Free up the updater alias
        $garbageAlias = Base64UrlSafe::encode(\random_bytes(63)) . '.phar';
        $updater->setAlias($garbageAlias);
        unset($updater);

        // Now bring it back up.
        $this->bringSiteBackUp();
    }

    /**
     * Unique to Airship updating; replace a file with one in the archive
     *
     * @param string $filename
     * @return bool
     */
    protected function replaceFile(string $filename): bool
    {
        if (\file_exists(ROOT . DIRECTORY_SEPARATOR . $filename . '.backup')) {
            \unlink(ROOT . DIRECTORY_SEPARATOR . $filename . '.backup');
        }
        \rename(
            ROOT . DIRECTORY_SEPARATOR . $filename,
            ROOT . DIRECTORY_SEPARATOR . $filename . '.backup'
        );
        return \file_put_contents(
            ROOT . DIRECTORY_SEPARATOR . $filename,
            \file_get_contents('phar://' . $this->pharAlias . '/'.$filename)
        ) !== false;
    }
}
