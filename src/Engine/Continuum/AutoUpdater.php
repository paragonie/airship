<?php
declare(strict_types=1);
namespace Airship\Engine\Continuum;

use Airship\Alerts\Continuum\CouldNotUpdate;
use Airship\Alerts\FileSystem\FileNotFound;
use Airship\Alerts\Hail\{
    NoAPIResponse,
    SignatureFailed
};
use Airship\Engine\{
    Continuum\Updaters\Cabin,
    Continuum\Updaters\UpdateFile,
    Continuum\Updaters\UpdateInfo,
    Bolt\Log as LogBolt,
    Hail,
    State
};
use GuzzleHttp\Exception\TransferException;
use ParagonIE\ConstantTime\Base64;
use ParagonIE\Halite\{
    File,
    Util
};
use Psr\Log\LogLevel;

/**
 * Class AutoUpdater
 *
 * The base class for the auto-updaters.
 *
 * @package Airship\Engine\Continuum
 */
abstract class AutoUpdater
{
    use LogBolt;

    const TYPE_ENGINE = 'engine';
    const TYPE_CABIN = 'cabin';
    const TYPE_GADGET = 'gadget';
    const TYPE_MOTIF = 'motif';

    /**
     * @var bool
     */
    protected $bypassSecurityAndJustInstall = false;

    /**
     * @var Channel[]
     */
    protected static $channels = [];

    /**
     * @var Log
     */
    protected static $continuumLogger;

    /**
     * @var string
     */
    protected $ext = 'txt';

    /**
     * @var string
     */
    protected $filePath = '';

    /**
     * @var Hail
     */
    protected $hail;

    /**
     * @var UpdateFile
     */
    protected $localUpdateFile;

    /**
     * @var array
     */
    protected $manifest = [];

    /**
     * @var string
     */
    protected $name = '';

    /**
     * @var string
     */
    protected $pharAlias;

    /**
     * @var Supplier
     */
    protected $supplier;

    /**
     * @var string
     */
    protected $type = '';

    /**
     * Automatic script execution
     *
     * @param array $autoRun
     * @return mixed
     */
    protected function autoRunScript(array $autoRun)
    {
        $ret = null;
        // Get a unique temporary file
        do {
            $script = \tempnam(ROOT . DIRECTORY_SEPARATOR . 'tmp', 'update-script-');
        } while (\file_exists($script));

        // What kind of autoRun script is it?
        switch ($autoRun['type']) {
            case 'php':
                \file_put_contents(
                    $script . '.php',
                    Base64::decode($autoRun['data'])
                );
                $ret = Sandbox::safeRequire($script . '.php');
                \unlink($script . '.php');
                break;
            case 'sh':
                \file_put_contents(
                    $script . '.sh',
                    Base64::decode($autoRun['data'])
                );
                $ret = \shell_exec($script . '.sh');
                \unlink($script . '.sh');
                break;
            case 'pgsql':
            case 'mysql':
                \file_put_contents(
                    $script . '.' . $autoRun['type'],
                    Base64::decode($autoRun['data'])
                );
                $ret = Sandbox::runSQLFile(
                    $script . '.' . $autoRun['type'],
                    $autoRun['type']
                );
                \unlink($script . '.' . $autoRun['type']);
                break;
        }
        return $ret;
    }

    /**
     * After we finish our update, we should bring the site back online:
     */
    protected function bringSiteBackUp()
    {
        \unlink(ROOT . '/tmp/site_down.txt');
        \clearstatcache();
    }

    /**
     * Let's bring the site down while we're upgrading:
     */
    protected function bringSiteDown()
    {
        \file_put_contents(
            ROOT . '/tmp/site_down.txt',
            \date(\AIRSHIP_DATE_FORMAT)
        );
        \clearstatcache();
    }

    /**
     * This is for manual installations and update scripts. It should
     * never be invoked automatically.
     *
     * @param bool $set
     * @return AutoUpdater
     */
    public function bypassSecurityAndJustInstall(bool $set = false): self
    {
        if ($set) {
            $this->log('Disabling the security verification', LogLevel::WARNING);
        }
        $this->bypassSecurityAndJustInstall = $set;
        return $this;
    }

    /**
     * Should this automatic update be permitted?
     *
     * @param UpdateInfo $info
     * @param string $currentVersion
     * @return bool
     */
    protected function checkVersionSettings(
        UpdateInfo $info,
        string $currentVersion
    ): bool {
        $state = State::instance();
        $nextVersion = $info->getVersion();
        $version = new Version($currentVersion);

        // If this isn't an upgrade at all, don't apply it.
        if (!$version->isUpgrade($nextVersion)) {
            return false;
        }
        if ($version->isMajorUpgrade($nextVersion)) {
            return !empty($state->universal['auto-update']['major']);
        }
        if ($version->isMinorUpgrade($nextVersion)) {
            return !empty($state->universal['auto-update']['minor']);
        }
        if ($version->isPatchUpgrade($nextVersion)) {
            return !empty($state->universal['auto-update']['patch']);
        }
        return false;
    }

    /**
     * Was the checksum of this update stored in Keyggdrasil?
     *
     * @param UpdateInfo $info
     * @param UpdateFile $file
     * @return bool
     */
    public function checkKeyggdrasil(UpdateInfo $info, UpdateFile $file): bool
    {
        $debugArgs = [
            'supplier' =>
                $info->getSupplierName(),
            'name' =>
                $info->getPackageName()
        ];
        $this->log('Checking Keyggdrasil', LogLevel::DEBUG, $debugArgs);
        $db = \Airship\get_database();
        $merkle = $db->row(
            'SELECT * FROM airship_tree_updates WHERE merkleroot = ?',
            $info->getMerkleRoot()
        );
        if (empty($merkle)) {
            $this->log('Merkle root not found in tree', LogLevel::DEBUG, $debugArgs);
            // Not found in Keyggdrasil
            return false;
        }

        $data = \Airship\parseJSON($merkle['data'], true);
        if (!\hash_equals($this->type, $data['pkg_type'])) {
            $this->log('Wrong package type', LogLevel::DEBUG, $debugArgs);
            // Wrong package type
            return false;
        }
        if (!\hash_equals($info->getSupplierName(), $data['supplier'])) {
            $this->log('Wrong supplier', LogLevel::DEBUG, $debugArgs);
            // Wrong supplier
            return false;
        }
        if (!\hash_equals($info->getPackageName(), $data['name'])) {
            $this->log('Wrong package', LogLevel::DEBUG, $debugArgs);
            // Wrong package
            return false;
        }
        // Finally, we verify that the checksum matches the entry in our Merkle tree:
        return \hash_equals($file->getHash(), $data['checksum']);
    }

    /**
     * Download an update into a temp file
     *
     * @param UpdateInfo $update
     * @param string $apiEndpoint
     * @return UpdateFile
     */
    public function downloadUpdateFile(
        UpdateInfo $update,
        string $apiEndpoint = 'download'
    ): UpdateFile {
        if ($this->localUpdateFile instanceof UpdateFile) {
            $this->log('Local update file used', LogLevel::DEBUG);
            return $this->localUpdateFile;
        }
        try {
            $version = $update->getVersion();
            $body = $this->hail->postReturnBody(
                $update->getChannel() . API::get($apiEndpoint),
                [
                    'type' => \get_class($this),
                    'supplier' => $update->getSupplierName(),
                    'package' => $update->getPackageName(),
                    'version' => $version
                ]
            );
            $outFile = \Airship\tempnam('airship-', $this->ext);
            $saved = \file_put_contents($outFile, $body);
            if ($saved !== false) {
                // To prevent TOCTOU issues down the line
                $hash = Util::hash($body);
                $body = null;
                \clearstatcache();

                return new UpdateFile([
                    'path' => $outFile,
                    'version' => $version,
                    'hash' => $hash,
                    'size' => \filesize($outFile)
                ]);
            }
            // If we're still here...
            throw new TransferException();
        } catch (TransferException $ex) {
            $this->log(
                'Automatic update failure.',
                LogLevel::WARNING,
                [
                    'exception' => \Airship\throwableToArray($ex),
                    'channel' => $update->getChannel()
                ]
            );
            // Rethrow it to prevent errors on return type
            throw $ex;
        }
    }

    /**
     * Get the channels
     *
     * @param string $name
     * @return Channel
     * @throws NoAPIResponse
     */
    protected function getChannel(string $name): Channel
    {
        if (empty(self::$channels)) {
            $config = \Airship\loadJSON(ROOT . '/config/channels.json');
            foreach ($config as $chName => $chConfig) {
                self::$channels[$chName] = new Channel($this, $chName, $chConfig);
            }
        }
        if (isset(self::$channels[$name])) {
            return self::$channels[$name];
        }
        throw new NoAPIResponse(
            \trk('errors.hail.no_channel_configured')
        );
    }

    /**
     * Get information for logging purposes
     *
     * @param UpdateInfo $updateInfo
     * @param UpdateFile $updateFile
     * @return array
     */
    protected function getLogContext(
        UpdateInfo $updateInfo,
        UpdateFile $updateFile
    ): array {
        return [
            'action' => 'UPDATE',
            'name' => $this->name,
            'supplier' => $this->supplier->getName(),
            'type' => $this->type,
            'updateFile' => [
                'checksum' => $updateFile->getHash(),
                'filesize' => $updateFile->getSize(),
                'version' => $updateFile->getVersion()
            ],
            'updateInfo' => [
                'name' => $updateInfo->getPackageName(),
                'supplier' => $updateInfo->getSupplierName(),
                'merkleRoot' => $updateInfo->getMerkleRoot(),
                'signature' => $updateInfo->getSignature(true),
                'version' => $updateInfo->getVersion()
            ]
        ];
    }

    /**
     * This is the method that actually does the installation
     *
     * @param UpdateInfo $info
     * @param UpdateFile $file
     * @throws CouldNotUpdate
     */
    abstract protected function install(UpdateInfo $info, UpdateFile $file);

    /**
     * Process manual updates:
     *
     * 1. Check if a new update is available.
     * 2. Download the upload file, store in a temporary file.
     * 3. Verify the signature (via Halite).
     * 4. Verify the update is recorded in Keyggdrasil.
     * 5. If all is well, run the update script.
     *
     * @param string $desiredVersion
     * @return bool
     */
    public function manualUpdate(string $desiredVersion): bool
    {
        $debugArgs = [
            'supplier' =>
                $this->supplier->getName(),
            'name' =>
                $this->name,
            'version' =>
                $desiredVersion
        ];
        $this->log('Begin Cabin manual update routine', LogLevel::DEBUG, $debugArgs);
        $found = false;

        if ($this instanceof Cabin) {
            if ($this->isAirshipSpecialCabin()) {
                // This only gets touched by core updates.
                return false;
            }
        }
        try {
            /**
             * @var UpdateInfo[]
             */
            $updates = $this->updateCheck(
                $this->supplier->getName(),
                $this->name,
                $this->manifest['version']
            );
            foreach ($updates as $updateInfo) {
                // We only want a specific version to be installed.
                if ($updateInfo->getVersion() !== $desiredVersion) {
                    continue;
                }
                $found = true;
                /**
                 * @var UpdateFile
                 */
                $updateFile = $this->downloadUpdateFile($updateInfo);
                $this->log('Downloaded Cabin update file', LogLevel::DEBUG, $debugArgs);

                if ($this->bypassSecurityAndJustInstall) {
                    $this->log('Cabin update verification bypassed', LogLevel::ALERT, $debugArgs);
                    $this->install($updateInfo, $updateFile);
                    return true;
                }

                /**
                 * Don't proceed unless we've verified the signatures
                 */
                if ($this->verifyUpdateSignature($updateInfo, $updateFile)) {
                    if ($this->checkKeyggdrasil($updateInfo, $updateFile)) {
                        $this->install($updateInfo, $updateFile);
                        return true;
                    } else {
                        $this->log('Keyggdrasil check failed for this Cabin', LogLevel::ALERT, $debugArgs);
                    }
                } else {
                    $this->log('Signature check failed for this Cabin', LogLevel::ALERT, $debugArgs);
                }
            }
        } catch (NoAPIResponse $ex) {
            // We should log this.
            $this->log(
                'Automatic update failure: NO API Response.',
                LogLevel::ERROR,
                \Airship\throwableToArray($ex)
            );
        }
        return $found;
    }

    /**
     * Sort updates by version (newest to latest)
     *
     * @param UpdateInfo[] ...$updates
     * @return UpdateInfo[]
     */
    protected function sortUpdatesByVersion(UpdateInfo ...$updates): array
    {
        \uasort(
            $updates,
            function(UpdateInfo $a, UpdateInfo $b): int
            {
                return $a->getVersionExpanded() <=> $b->getVersionExpanded();
            }
        );
        return $updates;
    }
    
    /**
     * Are any updates available?
     *
     * @param string $supplier
     * @param string $packageName
     * @param string $minVersion
     * @param string $apiEndpoint
     *
     * @return UpdateInfo[]
     *
     * @throws \Airship\Alerts\Hail\NoAPIResponse
     */
    public function updateCheck(
        string $supplier = '',
        string $packageName = '',
        string $minVersion = '',
        string $apiEndpoint = 'version'
    ): array {
        if (empty($supplier)) {
            $supplier = $this->supplier->getName();
        }
        $channelsConfigured = $this->supplier->getChannels();
        if (empty($channelsConfigured)) {
            throw new NoAPIResponse(
                \trk('errors.hail.no_channel_configured')
            );
        }
        foreach ($channelsConfigured as $channelName) {
            $channel = $this->getChannel($channelName);
            $publicKey = $channel->getPublicKey();
            foreach ($channel->getAllURLs() as $ch) {
                try {
                    $response = $this->hail->postSignedJSON(
                        $ch . API::get($apiEndpoint),
                        $publicKey,
                        [
                            'type' => $this->type,
                            'supplier' => $supplier,
                            'package' => $packageName,
                            'minimum' => $minVersion
                        ]
                    );
                    if ($response['status'] === 'error') {
                        $this->log(
                            $response['error'],
                            LogLevel::ERROR,
                            [
                                'response' => $response,
                                'channel' => $ch,
                                'supplier' => $supplier,
                                'type' => $this->type,
                                'package' => $packageName
                            ]
                        );
                        continue;
                    }
                    $updates = [];
                    foreach ($response['versions'] as $update) {
                        $updates [] = new UpdateInfo(
                            $update,
                            $ch,
                            $publicKey,
                            $supplier,
                            $packageName
                        );
                    }
                    if (empty($updates)) {
                        $this->log(
                            'No updates found.',
                            LogLevel::DEBUG,
                            [
                                'type' => \get_class($this),
                                'supplier' => $supplier,
                                'package' => $packageName,
                                'channelName' => $channelName,
                                'channel' => $ch
                            ]
                        );
                        return [];
                    }
                    return $this->sortUpdatesByVersion(...$updates);
                } catch (SignatureFailed $ex) {
                    // Log? Definitely suppress, however.
                    $this->log(
                        'Automatic update - signature failure. (' . \get_class($ex) . ')',
                        LogLevel::ALERT,
                        [
                            'exception' => \Airship\throwableToArray($ex),
                            'channelName' => $channelName,
                            'channel' => $ch
                        ]
                    );
                } catch (TransferException $ex) {
                    // Log? Definitely suppress, however.
                    $this->log(
                        'Automatic update failure. (' . \get_class($ex) . ')',
                        LogLevel::WARNING,
                        [
                            'exception' => \Airship\throwableToArray($ex),
                            'channelName' => $channelName,
                            'channel' => $ch
                        ]
                    );
                }
            }
        }
        throw new NoAPIResponse(
            \trk('errors.hail.no_channel_responded')
        );
    }

    /**
     * Update the version string in airship_package_cache
     *
     * @param string $type
     * @param UpdateInfo $info
     * @return bool
     */
    public function updateDBRecord(string $type, UpdateInfo $info): bool
    {
        $db = \Airship\get_database();
        $db->beginTransaction();
        $db->update(
            'airship_package_cache',
            [
                'current_version' =>
                    $info->getVersion()
            ],
            [
                'packagetype' =>
                    $type,
                'supplier' =>
                    $info->getSupplierName(),
                'name' =>
                    $info->getPackageName()
            ]
        );
        return $db->commit();
    }


    /**
     * For CLI usage: Bypass the download process, use a local file instead.
     *
     * @param string $path
     * @param string $version
     * @return AutoUpdater
     * @throws FileNotFound
     */
    public function useLocalUpdateFile(
        string $path,
        string $version = ''
    ): self {
        if (\file_exists($path)) {
            throw new FileNotFound();
        }
        $hash = File::checksum($path);
        $this->localUpdateFile = new UpdateFile([
            'path' => $path,
            'version' => $version,
            'hash' => $hash,
            'size' => \filesize($path)
        ]);
        return $this;
    }

    /**
     * Verify the Ed25519 signature of the update file against the
     * supplier's public key.
     *
     * @param UpdateInfo $info
     * @param UpdateFile $file
     * @return bool
     */
    public function verifyUpdateSignature(
        UpdateInfo $info,
        UpdateFile $file
    ): bool {
        $debugArgs = [
            'path' =>
                $file->getPath(),
            'supplier' =>
                $info->getSupplierName(),
            'name' =>
                $info->getPackageName()
        ];
        $this->log('Checking update signature...', LogLevel::DEBUG, $debugArgs);
        $ret = false;
        foreach ($this->supplier->getSigningKeys() as $key) {
            if ($key['type'] !== 'signing') {
                continue;
            }
            $ret = $ret ||  File::verify(
                $file->getPath(),
                $key['key'],
                $info->getSignature(true)
            );
        }
        $this->log('Signature result: ' . ($ret ? 'true' : 'false'), LogLevel::DEBUG, $debugArgs);
        return $ret;
    }
}
