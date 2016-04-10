<?php
declare(strict_types=1);
namespace Airship\Engine\Continuum;

use \Airship\Alerts\Hail\NoAPIResponse;
use \Airship\Engine\State;
use \Airship\Engine\Bolt\Log;
use \ParagonIE\Halite\File;
use \ParagonIE\ConstantTime\Base64;
use \GuzzleHttp\Psr7\Response;
use \GuzzleHttp\Exception\TransferException;
use \Psr\Log\LogLevel;

abstract class AutoUpdater
{
    use Log;

    protected $pharAlias;
    protected $name;
    protected $supplier;
    protected $hail;

    /**
     * Automatic script execution
     *
     * @param array $autorun
     * @return mixed
     */
    protected function autorunScript(array $autorun)
    {
        $ret = null;
        // Get a unique temporary file
        do {
            $script = \tempnam(ROOT . DIRECTORY_SEPARATOR . 'tmp', 'update-script-');
        } while (\file_exists($script));

        // What kind of autorun script is it?
        switch ($autorun['type']) {
            case 'php':
                \file_put_contents($script.'.php', Base64::decode($autorun['data']));
                $ret = Sandbox::safeRequire($script.'.php');
                \unlink($script.'.php');
                break;
            case 'sh':
                \file_put_contents($script.'.sh', Base64::decode($autorun['data']));
                $ret = \shell_exec($script.'.sh');
                \unlink($script.'.sh');
                break;
            case 'pgsql':
            case 'mysql':
                \file_put_contents($script.'.'.$autorun['type'], Base64::decode($autorun['data']));
                $ret = Sandbox::runSQLFile($script.'.'.$autorun['type'], $autorun['type']);
                \unlink($script.'.'.$autorun['type']);
                break;
        }
        return $ret;
    }

    /**
     * After we finish our update, we should bring the site back online:
     */
    protected function bringSiteBackUp()
    {
        \unlink(
            ROOT.DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'site_down.txt'
        );
        \clearstatcache();
    }

    /**
     * Let's bring the site down while we're upgrading:
     */
    protected function bringSiteDown()
    {
        \file_put_contents(
            ROOT.DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'site_down.txt',
            \date('Y-m-d\TH:i:s')
        );
        \clearstatcache();
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
        try {
            $version = $update->getVersion();
            $response = $this->hail->post(
                $update->getChannel() . API::get($apiEndpoint),
                [
                    'version' => $version
                ]
            );
            if ($response instanceof Response) {
                $outFile = \tempnam(\sys_get_temp_dir(), 'airship-') . '.phar';
                $code = $response->getStatusCode();
                if ($code >= 200 && $code < 300) {
                    $body = (string) $response->getBody();
                    $saved = \file_put_contents($outFile, $body);
                    if ($saved !== false) {
                        // To prevent TOCTOU issues down the line
                        $hash = \Sodium\bin2hex(\Sodium\crypto_generichash($body));
                        $body = null;
                        \clearstatcache();
                        return new UpdateFile([
                            'path' => $outFile,
                            'version' => $version,
                            'hash' => $hash,
                            'size' => \filesize($outFile)
                        ]);
                    }
                }
            }
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
        string $supplier,
        string $packageName,
        string $minVersion = '',
        string $apiEndpoint = 'version'
    ): array {
        $channels = $this->supplier->getChannels();
        if (empty($channels)) {
            throw new NoAPIResponse(
                \trk('errors.hail.no_channel_configured')
            );
        }

        foreach ($channels as $ch) {
            try {
                $response = $this->hail->post(
                    $ch . API::get($apiEndpoint),
                    [
                        'supplier' => $supplier,
                        'package' => $packageName,
                        'minimum' => $minVersion
                    ]
                );
                if ($response instanceof Response) {
                    $code = $response->getStatusCode();
                    if ($code >= 200 && $code < 300) {
                        $body = \json_decode((string) $response->getBody(), true);
                        $updates = [];
                        foreach ($body['versions'] as $update) {
                            $updates []= new UpdateInfo(
                                $update,
                                $ch
                            );
                        }
                        return $updates;
                    }
                }
            } catch (TransferException $ex) {
                // Log? Definitely suppress, however.
                $this->log(
                    'Automatic update failure. (' . \get_class($ex) . ')',
                    LogLevel::WARNING,
                    [
                        'exception' => \Airship\throwableToArray($ex),
                        'channel' => $ch
                    ]
                );
            }
        }
        throw new NoAPIResponse(
            \trk('errors.hail.no_channel_responded')
        );
    }

    /**
     * @todo In a future release (possibly 1.0.0) we should allow developers to
     * add "peers" whom they trust to attest for the authenticity of each core
     * software update to prevent targeted attacks. For now, let's just return
     * true knowing this can be built later.
     *
     * @param UpdateInfo $info
     * @param UpdateFile $file
     * @return bool
     */
    public function verifyChecksumWithPeers(
        UpdateInfo $info,
        UpdateFile $file
    ): bool {
        return true;
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
        $ret = false;
        foreach ($this->supplier->getSigningKeys() as $key) {
            $ret = $ret || File::verify(
                $file->getPath(),
                $key['key'],
                $info->getSignature(true)
            );
        }
        return $ret;
    }
}
