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
    Hail,
    State
};
use ParagonIE\ConstantTime\Base64UrlSafe;
use Psr\Log\LogLevel;

/**
 * Class Cabin
 *
 * This updates a Cabin.
 *
 * @package Airship\Engine\Continuum
 */
class Cabin extends AutoUpdater implements ContinuumInterface
{
    /**
     * @var array
     */
    protected $cabin = [];

    /**
     * @var string
     */
    protected $ext = 'phar';

    // These are excluded. See Airship.php instead.
    const AIRSHIP_SPECIAL_CABINS = ['Hull', 'Bridge'];

    /**
     * Cabin constructor.
     *
     * @param Hail $hail
     * @param array $manifest
     * @param Supplier $supplier
     */
    public function __construct(
        Hail $hail,
        array $manifest,
        Supplier $supplier
    ) {
        $this->name = $manifest['name'];
        $this->manifest = $manifest;
        $this->supplier = $supplier;
        $this->hail = $hail;
        $this->pharAlias = 'cabin.' . $this->name . '.phar';
        $this->type = self::TYPE_CABIN;
        if (!self::$continuumLogger) {
            self::$continuumLogger = new Log();
        }
    }

    /**
     * Is this cabin part of the Airship core? (They don't
     * get automatically updated separate from the core.)
     *
     * @return bool
     */
    public function isAirshipSpecialCabin(): bool
    {
        $state = State::instance();
        return (
            $this->supplier->getName() === $state->universal['airship']['trusted-supplier']
                &&
            \in_array($this->name, self::AIRSHIP_SPECIAL_CABINS)
        );
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
        $this->log('Begin Cabin auto-update routine', LogLevel::DEBUG, $debugArgs);
        if ($this->isAirshipSpecialCabin()) {
            // This only gets touched by core updates.
            return;
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
                if (!$this->checkVersionSettings($updateInfo, $this->manifest['version'])) {
                    $this->log(
                        'Skipping Cabin update',
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
                $this->log('Downloaded Cabin update file', LogLevel::DEBUG, $debugArgs);

                if ($this->bypassSecurityAndJustInstall) {
                    $this->log('Cabin update verification bypassed', LogLevel::ALERT, $debugArgs);
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
                        $this->log('Keyggdrasil check failed for this Cabin', LogLevel::ALERT, $debugArgs);
                        self::$continuumLogger->store(
                            LogLevel::ALERT,
                            'Cabin update failed -- checksum not registered in Keyggdrasil',
                            $this->getLogContext($updateInfo, $updateFile)
                        );
                    }
                } else {
                    $this->log('Signature check failed for this Cabin', LogLevel::ALERT, $debugArgs);
                    self::$continuumLogger->store(
                        LogLevel::ALERT,
                        'Cabin update failed -- invalid signature',
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
                'Cabin update failed -- no API Response',
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
     * Install an updated version of a cabin
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
     * @return void
     */
    protected function install(UpdateInfo $info, UpdateFile $file)
    {
        if (!$file->hashMatches($info->getChecksum())) {
            throw new CouldNotUpdate(
                \__('Checksum mismatched')
            );
        }
        $path = $file->getPath();
        $this->log(
            'Begin Cabin updater',
            LogLevel::DEBUG,
            [
                'path' =>
                    $path,
                'supplier' =>
                    $info->getSupplierName(),
                'name' =>
                    $info->getPackageName()
            ]
        );
        $updater = new \Phar(
            $path,
            \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::KEY_AS_FILENAME
        );
        $updater->setAlias($this->pharAlias);

        $ns = $this->makeNamespace($info->getSupplierName(), $info->getPackageName());

        // We need to do this while we're replacing files.
        $this->bringCabinDown($ns);
        $oldMetadata = \Airship\loadJSON(ROOT . '/Cabin/' . $ns . '/manifest.json');

        // Overwrite files
        $updater->extractTo(ROOT . '/Cabin/' . $ns, [], true);

        // Run the update trigger.
        Sandbox::safeInclude('phar://' . $this->pharAlias . '/update_trigger.php', $oldMetadata);

        // Free up the updater alias
        $garbageAlias = Base64UrlSafe::encode(\random_bytes(33)) . '.phar';
        $updater->setAlias($garbageAlias);
        unset($updater);

        // Now bring it back up.
        $this->bringCabinBackUp($ns);

        // Make sure we update the version info. in the DB cache:
        $this->updateDBRecord('Cabin', $info);

        $this->log(
            'Conclude Cabin updater',
            LogLevel::DEBUG,
            [
                'path' =>
                    $path,
                'supplier' =>
                    $info->getSupplierName(),
                'name' =>
                    $info->getPackageName()
            ]
        );
        self::$continuumLogger->store(
            LogLevel::INFO,
            'Cabin update installed',
            $this->getLogContext($info, $file)
        );
    }

    /**
     * Unique to cabin updating; replace a file with one in the archive
     *
     * @param string $filename
     * @return int|bool
     */
    protected function replaceFile(string $filename)
    {
        $supplier = $this->supplier->getName();
        $pieces = [
            ROOT,
            'Cabin',
            $supplier,
            $this->name
        ];
        $cabinRoot = \implode(DIRECTORY_SEPARATOR, $pieces);
        if (\file_exists($cabinRoot . DIRECTORY_SEPARATOR . $filename . '.backup')) {
            \unlink($cabinRoot . DIRECTORY_SEPARATOR . $filename . '.backup');
        }
        \rename(
            $cabinRoot . DIRECTORY_SEPARATOR . $filename,
            $cabinRoot . DIRECTORY_SEPARATOR . $filename . '.backup'
        );
        return \file_put_contents(
            $cabinRoot . DIRECTORY_SEPARATOR . $filename,
            \file_get_contents('phar://' . $this->pharAlias . '/'.$filename)
        );
    }

    /**
     * After we finish our update, we should bring the cabin back online:
     *
     * @param string $name Cabin name
     * @return void
     */
    protected function bringCabinBackUp(string $name = '')
    {
        \unlink(
            \implode(
                DIRECTORY_SEPARATOR,
                [
                    ROOT,
                    'tmp',
                    'cabin.' . $name . '.offline.txt'
                ]
            )
        );
        // Clear caches:
        \unlink(
            \implode(
                DIRECTORY_SEPARATOR,
                [
                    ROOT,
                    'tmp',
                    'cargo',
                    'cabin_data.json'
                ]
            )
        );
        \unlink(
            \implode(
                DIRECTORY_SEPARATOR,
                [
                    ROOT,
                    'tmp',
                    'cache',
                    'cargo-' . $name . '.cache.json'
                ]
            )
        );
        \unlink(
            \implode(
                DIRECTORY_SEPARATOR,
                [
                    ROOT,
                    'tmp',
                    'cache',
                    'csp.' . $name . '.json'
                ]
            )
        );
        \unlink(
            \implode(
                DIRECTORY_SEPARATOR,
                [
                    ROOT,
                    'tmp',
                    'cache',
                    $name . '.motifs.json'
                ]
            )
        );
        \clearstatcache();
    }

    /**
     * Let's bring the cabin down while we're upgrading:
     *
     * @param string $name Cabin name
     * @return void
     */
    protected function bringCabinDown(string $name = '')
    {
        \file_put_contents(
            \implode(
                DIRECTORY_SEPARATOR,
                [
                    ROOT,
                    'tmp',
                    'cabin.' . $name . '.offline.txt'
                ]
            ),
            \date(\AIRSHIP_DATE_FORMAT)
        );
        \clearstatcache();
    }

    /**
     * some-test-user/cabin--for-the-win =>
     * Some_Test_User__Cabin_For_The_Win
     *
     * @param string $supplier
     * @param string $cabin
     * @return string
     */
    protected function makeNamespace(string $supplier, string $cabin): string
    {
        $supplier = \preg_replace('/[^A-Za-z0-9_]/', '_', $supplier);
        $exp = \explode('_', $supplier);
        $supplier = \implode('_', \array_map('ucfirst', $exp));
        $supplier = \preg_replace('/_{2,}/', '_', $supplier);

        $cabin = \preg_replace('/[^A-Za-z0-9_]/', '_', $cabin);
        $exp = \explode('_', $cabin);
        $cabin = \implode('_', \array_map('ucfirst', $exp));
        $cabin = \preg_replace('/_{2,}/', '_', $cabin);

        return \implode('__',
            [
                \trim($supplier, '_'),
                \trim($cabin, '_')
            ]
        );
    }
}
