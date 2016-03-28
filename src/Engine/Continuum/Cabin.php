<?php
declare(strict_types=1);
namespace Airship\Engine\Continuum;

use \Airship\Engine\Contract\ContinuumInterface;
use \Airship\Engine\Continuum\{
    UpdateFile,
    UpdateInfo
};

class Cabin extends AutoUpdater implements ContinuumInterface
{
    private $name;
    private $supplier;
    private $hail;
    
    public function __construct(
        \Airship\Engine\Hail $hail,
        array $manifest,
        Supplier $supplier
    ) {
        $this->name = $manifest['name'];
        $this->supplier = $supplier;
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
        try {
            $updateInfo = $this->updateCheck(
                $this->supplier->getName(),
                $this->name,
                $this->manifest['version']
            );
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
        } catch (NoAPIResponse $ex) {
            // We should log this.
            $this->log(
                'Automatic update failure.',
                \Psr\Log\LogLevel::CRITICAL,
                \Airship\throwableToArray($ex)
            );
        }
    }

    protected function install(UpdateInfo $info, UpdateFile $file)
    {
        // @todo
        echo 'TODO - Cabin', "\n";
    }
}
