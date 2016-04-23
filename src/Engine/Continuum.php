<?php
declare(strict_types=1);
namespace Airship\Engine;

use \Airship\Engine\Continuum\{
    Airship as AirshipUpdater,
    Cabin as CabinUpdater,
    Gadget as GadgetUpdater,
    Motif as MotifUpdater
};
use \Airship\Engine\Bolt\Supplier as SupplierBolt;

/**
 * Class Continuum
 *
 * This class controls the Continuum update process.
 *
 * @package Airship\Engine
 */
class Continuum
{
    use SupplierBolt;

    protected $hail;
    public $supplierCache;
    
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
            return (time() - $last) > $config->universal['auto-update']['check'];
        }
        return true;
    }
    
    /**
     * Do we need to do an update check?
     * 
     * @param bool $force Force start the update check?
     * 
     */
    public function checkForUpdates(bool $force = false)
    {
        $update = $force
            ? true
            : $this->needsUpdate();
        
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
     */
    public function doUpdateCheck()
    {
        $config = State::instance();
        // First, update each cabin
        foreach ($this->getCabins() as $cabin) {
            if ($cabin instanceof CabinUpdater) {
                $cabin->autoUpdate();
            }
        }
        // Next, update each gadget
        foreach ($this->getGadgets() as $gadget) {
            if ($gadget instanceof GadgetUpdater) {
                $gadget->autoUpdate();
            }
        }
        // Also, motifs:
        foreach ($this->getMotifs() as $motif) {
            if ($motif instanceof MotifUpdater) {
                $motif->autoUpdate();
            }
        }
        // Finally, let's update the core
        $s = $config->universal['airship']['trusted-supplier'];
        if (!empty($s)) {
            $ha = new AirshipUpdater(
                $this->hail,
                $this->getSupplier($s)
            );
            $ha->autoUpdate();
        }
    }
    
    /**
     * Get an array of CabinUpdater objects
     * 
     * @return CabinUpdater[]
     */
    public function getCabins(): array
    {
        $cabins = [];
        foreach (\Airship\list_all_files(ROOT.'/Cabin/') as $file) {
            if (\is_dir($file) && \is_readable($file.'/manifest.json')) {
                $manifest = \Airship\loadJSON($file.'/manifest.json');
                $dirName = \preg_replace('#^.+?/([^\/]+)$#', '$1', $file);
                if (!empty($manifest['supplier'])) {
                    $cabins[$dirName] = new CabinUpdater(
                        $this->hail,
                        $manifest,
                        $this->getSupplier($manifest['supplier'])
                    );
                }
            }
        }
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
        foreach (\Airship\list_all_files(ROOT.'/Cabin/') as $dir) {
            $cabinInfo = \Airship\loadJSON($dir . '/manifest.json');
            if (\is_dir($dir.'/Gadgets')) {
                foreach (\Airship\list_all_files($dir.'/Gadgets/', 'phar') as $file) {
                    $manifest = $this->getPharManifest($file);
                    $name = \preg_replace('#^.+?/([^\/]+)\.phar$#', '$1', $file);
                    $gadgets[$name] = new GadgetUpdater(
                        $this->hail,
                        $manifest,
                        $this->getSupplier($manifest['supplier']),
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
            $gadgets[$name] = new GadgetUpdater(
                $this->hail,
                $manifest,
                $this->getSupplier($manifest['supplier']),
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
                $motifName = $this->getEndPiece($motifDir);
                $manifest = \Airship\loadJSON($motifDir . '/motif.json');
                $name = $supplier . '.' . $motifName;
                $motifs[$name] = new MotifUpdater(
                    $this->hail,
                    $manifest,
                    $this->getSupplier($manifest['supplier'])
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
        $meta = $phar->getMetadata();
        if (empty($meta)) {
            /** @todo handle edge cases here if people neglect to use barge */
            return [];
        }
        return $meta;
    }

    /**
     * Get the last piece of a path
     *
     * @param string $fullPath
     * @return string
     */
    private function getEndPiece(string $fullPath): string
    {
        $arr = \explode('/', \trim('/', $fullPath));
        return \array_pop($arr);
    }
}
