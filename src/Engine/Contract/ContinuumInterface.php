<?php
declare(strict_types=1);
namespace Airship\Engine\Contract;

use \Airship\Engine\Continuum\Updaters\{
    UpdateFile,
    UpdateInfo
};

/**
 * Interface ContinuumInterface
 * @package Airship\Engine\Contract
 */
interface ContinuumInterface
{
    /**
     * Process automatic updates:
     * 
     * 1. Check if a new update is available.
     * 2. Download the upload file, store in a temporary file.
     * 3. Verify the signature (via Halite).
     * 4. If all is well, run the update script.
     */
    public function autoUpdate();

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
    ): UpdateFile;

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
    ): bool;
}
