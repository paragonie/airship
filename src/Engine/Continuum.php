<?php
declare(strict_types=1);
namespace Airship\Engine;

use Airship\Engine\Continuum\Supplier;
use Airship\Engine\Continuum\Updaters\{
    Airship as AirshipUpdater,
    Cabin as CabinUpdater,
    Gadget as GadgetUpdater,
    Motif as MotifUpdater
};
use Airship\Engine\Bolt\Log as LogBolt;
use Airship\Engine\Bolt\Supplier as SupplierBolt;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Psr\Log\LogLevel;

/**
 * Class Continuum
 *
 * This class controls the Continuum update process.
 *
 * @package Airship\Engine
 */
class Continuum
{
    use LogBolt;
    use SupplierBolt;

    /**
     * @var Hail
     */
    protected $hail;

    /**
     * @var Supplier[]
     */
    public $supplierCache;

    /**
     * Continuum constructor.
     * @param Hail|null $hail
     */
    public function __construct(Hail $hail = null)
    {
        $config = State::instance();
        if (empty($hail)) {
            $this->hail = $config->hail;
        } else {
            $this->hail = $hail;
        }
    }
    
    /**
     * Do we need to run the update process?
     * 
     * @return bool
     */
    public function needsUpdate(): bool
    {
        $config = State::instance();

        $path = \implode(
            DIRECTORY_SEPARATOR,
            [
                ROOT,
                'tmp',
                'last_update_check.txt'
            ]
        );
        if (\is_readable($path)) {
            $last = \file_get_contents($path);
            if (!\is_string($last)) {
                return true;
            }
            return (time() - (int) $last) > (int) $config->universal['auto-update']['check'];
        }
        return true;
    }
    
    /**
     * Do we need to do an update check? If so, start the update check process.
     * 
     * @param bool $force Force start the update check?
     * @return void
     */
    public function checkForUpdates(bool $force = false): void
    {
        $update = $force || $this->needsUpdate();
        
        if ($update) {
            // Load all the suppliers
            $this->getSupplier();
            
            // Actually perform the update check
            $this->doUpdateCheck();
        }
    }

    /**
     * Do the update check,
     *
     * 1. Update all cabins
     * 2. Update all gadgets
     * 3. Update the core
     *
     * @return void
     */
    public function doUpdateCheck(): void
    {
        $config = State::instance();
        // First, update each cabin
        foreach ($this->getCabins() as $_cabin) {
            /**
             * @var CabinUpdater
             */
            $cabin = $_cabin;
            if ($cabin instanceof CabinUpdater) {
                $cabin->autoUpdate();
            }
        }
        // Next, update each gadget
        foreach ($this->getGadgets() as $_gadget) {
            /**
             * @var GadgetUpdater
             */
            $gadget = $_gadget;
            if ($gadget instanceof GadgetUpdater) {
                $gadget->autoUpdate();
            }
        }
        // Also, motifs:
        foreach ($this->getMotifs() as $_motif) {
            /**
             * @var MotifUpdater
             */
            $motif = $_motif;
            if ($motif instanceof MotifUpdater) {
                $motif->autoUpdate();
            }
        }
        // Finally, let's update the core
        $s = $config->universal['airship']['trusted-supplier'];
        if (!empty($s)) {
            /**
             * @var Supplier
             */
            $supplierObj = $this->getSupplier($s);
            $ha = new AirshipUpdater(
                $this->hail,
                $supplierObj
            );
            $ha->autoUpdate();
        }
    }
    
    /**
     * Get an array of CabinUpdater objects
     * 
     * @return array<string, CabinUpdater>
     */
    public function getCabins(): array
    {
        $cabins = [];
        foreach (\glob(ROOT.'/Cabin/*') as $file) {
            if ($file === ROOT . '/Cabin/Bridge') {
                continue;
            }
            if ($file === ROOT . '/Cabin/Hull') {
                continue;
            }
            if (\is_dir($file) && \is_readable($file.'/manifest.json')) {
                $manifest = \Airship\loadJSON($file.'/manifest.json');
                $dirName = \preg_replace('#^.+?/([^\/]+)$#', '$1', $file);
                if (!empty($manifest['supplier'])) {
                    /**
                     * @var Supplier
                     */
                    $supplierObj = $this->getSupplier($manifest['supplier']);
                    $cabins[$dirName] = new CabinUpdater(
                        $this->hail,
                        $manifest,
                        $supplierObj
                    );
                }
            }
        }
        $this->log(
            'Retrieving cabins',
            LogLevel::DEBUG,
            [
                'cabins' =>
                    \array_keys($cabins)
            ]
        );
        return $cabins;
    }
    
    /**
     * Get an array of GadgetUpdater objects
     * 
     * @return GadgetUpdater[]
     */
    public function getGadgets(): array
    {
        $gadgets = [];
        // First, each cabin's gadgets:
        foreach (\glob(ROOT.'/Cabin/*') as $dir) {
            if (!\is_dir($dir)) {
                continue;
            }
            $cabinInfo = \Airship\loadJSON($dir . '/manifest.json');
            if (\is_dir($dir.'/Gadgets')) {
                foreach (\Airship\list_all_files($dir.'/Gadgets/', 'phar') as $file) {
                    $manifest = $this->getPharManifest($file);
                    $name = \preg_replace('#^.+?/([^\/]+)\.phar$#', '$1', $file);
                    /**
                     * @var Supplier
                     */
                    $supplier = $this->getSupplier($manifest['supplier']);
                    $gadgets[$name] = new GadgetUpdater(
                        $this->hail,
                        $manifest,
                        $supplier,
                        $file
                    );
                    $gadgets[$name]->setCabin(
                        $cabinInfo['supplier'],
                        $cabinInfo['name']
                    );
                }
            }
        }
        //  Then, the universal gadgets:
        foreach (\Airship\list_all_files(ROOT.'/Gadgets/', 'phar') as $file) {
            $manifest = $this->getPharManifest($file);
            $name = \preg_replace('#^.+?/([^\/]+)\.phar$#', '$1', $file);
            $orig = ''.$name;
            // Handle name collisions
            while (isset($gadgets[$name])) {
                $i = isset($i) ? ++$i : 2;
                $name = $orig . '-' . $i;
            }
            /**
             * @var Supplier
             */
            $supplierObj = $this->getSupplier($manifest['supplier']);
            $gadgets[$name] = new GadgetUpdater(
                $this->hail,
                $manifest,
                $supplierObj,
                $file
            );
        }
        return $gadgets;
    }

    /**
     * Get an array of GadgetUpdater objects
     *
     * @return MotifUpdater[]
     */
    public function getMotifs(): array
    {
        $motifs = [];
        // First the universal gadgets:
        foreach (\glob(ROOT . '/Motifs/*') as $supplierPath) {
            if (!\is_dir($supplierPath)) {
                continue;
            }
            $supplier = $this->getEndPiece($supplierPath);
            foreach (\glob($supplierPath . '/*') as $motifDir) {
                if ($motifDir === ROOT . '/Motifs/paragonie/airship-classic') {
                    continue;
                }
                $motifName = $this->getEndPiece($motifDir);
                $manifest = \Airship\loadJSON($motifDir . '/motif.json');
                $name = $supplier . '.' . $motifName;
                /**
                 * @var Supplier
                 */
                $supplierObj = $this->getSupplier($manifest['supplier']);
                $motifs[$name] = new MotifUpdater(
                    $this->hail,
                    $manifest,
                    $supplierObj
                );
            }
        }
        return $motifs;
    }

    /**
     * Get metadata from the Phar
     *
     * @param string $file
     * @return array
     */
    public function getPharManifest(string $file): array
    {
        $phar = new \Phar($file);
        $phar->setAlias(Base64UrlSafe::encode(\random_bytes(33)));
        $meta = $phar->getMetadata();
        if (empty($meta)) {
            return [];
        }
        return $meta;
    }
}
