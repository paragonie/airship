<?php
declare(strict_types=1);
namespace Airship\Engine\Continuum;

use \Airship\Alerts\Continuum\CouldNotUpdate;
use \Airship\Alerts\Hail\NoAPIResponse;
use Airship\Engine\{
    Contract\ContinuumInterface, Hail, State
};
use \ParagonIE\ConstantTime\Base64UrlSafe;
use \Psr\Log\LogLevel;

/**
 * Class Cabin
 * @package Airship\Engine\Continuum
 */
class Cabin extends AutoUpdater implements ContinuumInterface
{
    protected $hail;
    protected $cabin;
    protected $name;
    protected $supplier;
    protected $filePath;
    protected $manifest;

    // These are excluded. See Airship.php instead.
    const AIRSHIP_SPECIAL_CABINS = ['Hull', 'Bridge'];
    
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
    }

    /**
     * @return bool
     */
    protected function isAirshipSpecialCabin(): bool
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
     * 4. If all is well, run the update script.
     */
    public function autoUpdate()
    {
        if ($this->isAirshipSpecialCabin()) {
            // This only gets touched by core updates.
            return;
        }
        try {
            $updates = $this->updateCheck(
                $this->supplier->getName(),
                $this->name,
                $this->manifest['version']
            );
            foreach ($updates as $updateInfo) {
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
     * Install an updated version of a cabin
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
        $path = $file->getPath();
        $updater = new \Phar(
            $path,
            \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::KEY_AS_FILENAME
        );
        $updater->setAlias($this->pharAlias);
        $metadata = \json_decode($updater->getMetadata(), true);

        // We need to do this while we're replacing files.
        $this->bringCabinDown();

        if (isset($metadata['files'])) {
            foreach ($metadata['files'] as $fileName) {
                $this->replaceFile($fileName);
            }
        }
        if (isset($metadata['autorun'])) {
            foreach ($metadata['autorun'] as $autoRun) {
                $this->autoRunScript($autoRun);
            }
        }

        // Free up the updater alias
        $garbageAlias = Base64UrlSafe::encode(\random_bytes(33)) . '.phar';
        $updater->setAlias($garbageAlias);
        unset($updater);

        // Now bring it back up.
        $this->bringCabinBackUp();
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
     */
    protected function bringCabinBackUp()
    {
        \unlink(
            \implode(
                DIRECTORY_SEPARATOR,
                [
                    ROOT,
                    'tmp',
                    'cabin.'.$this->name.'.offline.txt'
                ]
            )
        );
        \clearstatcache();
    }

    /**
     * Let's bring the cabin down while we're upgrading:
     */
    protected function bringCabinDown()
    {
        \file_put_contents(
            \implode(
                DIRECTORY_SEPARATOR,
                [
                    ROOT,
                    'tmp',
                    'cabin.'.$this->name.'.offline.txt'
                ]
            ),
            \date('Y-m-d\TH:i:s')
        );
        \clearstatcache();
    }
}
