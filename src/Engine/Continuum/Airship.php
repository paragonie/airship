<?php
declare(strict_types=1);
namespace Airship\Engine\Continuum;

use \Airship\Engine\Contract\ContinuumInterface;
use \Airship\Alerts\Hail\{
    NoAPIResponse,
    PeerVerificationFailure
};
use \Airship\Engine\Continuum\{
    UpdateFile,
    UpdateInfo
};
use \ParagonIE\ConstantTime\Base64UrlSafe;

class Airship extends AutoUpdater implements ContinuumInterface
{
    protected $pharAlias = 'airship-update.phar';
    protected $name = 'airship'; // Package name
    
    public function __construct(\Airship\Engine\Hail $hail, Supplier $sup)
    {
        $this->supplier = $sup;
        $this->hail = $hail;
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
        $state = \Airship\Engine\State::instance();
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
                        \Psr\Log\LogLevel::INFO,
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
                    $this->install($updateInfo, $updateFile);
                }
            }
        } catch (NoAPIResponse $ex) {
            // We should log this.
            $this->log(
                'Automatic update failure.',
                \Psr\Log\LogLevel::CRITICAL,
                \Airship\throwableToArray($ex)
            );
        }
    }

    /**
     * Let's install the automatic update.
     *
     * @param UpdateInfo $info
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
                    'Airship Core'
                )
            );
        }
        $path = $file->getPath();
        $updater = new \Phar(
            $path,
            \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::KEY_AS_FILENAME
        );
        $updater->setAlias($this->pharAlias);
        $metadata = \json_decode($updater->getMetadata(), true);
        $this->bringSiteDown();

        if (isset($metadata['files'])) {
            foreach ($metadata['files'] as $filename) {
                $this->replaceFile($filename);
            }
        }
        if (isset($metadata['autorun'])) {
            foreach ($metadata['autorun'] as $autorun) {
                $this->autorunScript($autorun);
            }
        }

        // Free up the updater alias
        $garbageAlias =
            Base64UrlSafe::encode(\random_bytes(33)) . '.phar';
        $updater->setAlias($garbageAlias);
        unset($updater);

        $this->bringSiteBackUp();
    }

    /**
     * Unique to Airship updating; replace a file with one in the archive
     *
     * @param string $filename
     * @return int|bool
     */
    protected function replaceFile(string $filename)
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
        );
    }
}
