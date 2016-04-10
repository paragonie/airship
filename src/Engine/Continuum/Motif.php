<?php
declare(strict_types=1);
namespace Airship\Engine\Continuum;

use \Airship\Alerts\Hail\NoAPIResponse;
use \Airship\Engine\Contract\ContinuumInterface;
use \Airship\Engine\Hail;
use \Psr\Log\LogLevel;

class Motif extends AutoUpdater implements ContinuumInterface
{
    private $hail;
    private $name;
    private $supplier;
    private $filePath;
    private $manifest;

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
                $res = $updateInfo->getResponse();
                if ($res['status'] !== 'error') {
                    $updateFile = $this->downloadUpdateFile($updateInfo);
                    /**
                     * Don't proceed unless we've verified the signatures
                     */
                    if ($this->verifyUpdateSignature($updateInfo, $updateFile)) {
                        return $this->install($updateInfo, $updateFile);
                    }
                }
            }
        } catch (NoAPIResponse $ex) {
            // We should log this.
            $this->log(
                'Automatic update failure.',
                LogLevel::CRITICAL,
                \Airship\throwableToArray($ex)
            );
        }
    }

    protected function install(UpdateInfo $info, UpdateFile $file)
    {
        // @todo - extract the .zip over the old Motif
    }
}
