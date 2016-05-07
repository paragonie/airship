<?php
declare(strict_types=1);
namespace Airship\Engine\Bolt;

use \Airship\Alerts\Continuum\NoSupplier;
use \Airship\Alerts\FileSystem\FileNotFound;
use \Airship\Engine\Continuum\Supplier as SupplierObject;

/**
 * Trait Supplier
 *
 * This is used by both Continuum and Keyggdrasil in precisely the same manner
 *
 * @package Airship\Engine\Bolt
 */
trait Supplier
{
    /**
     * This is used when saving a new supplier configuration
     * for the very first time.
     *
     * @param string $channelName
     * @param array $data
     * @return SupplierObject
     */
    public function createSupplier(string $channelName, array $data): SupplierObject
    {
        $supplierName = $data['supplier'];
        
        \file_put_contents(
            ROOT . '/config/supplier_keys/' . $supplierName . '.json',
            \json_encode(
                [
                    'channels' => [
                        $channelName
                    ],
                    'signing_keys' => []
                ],
                JSON_PRETTY_PRINT
            )
        );
    }

    /**
     * Load all of the supplier's Ed25519 public keys
     *
     * @param string $supplier
     * @param boolean $force_flush
     * @return SupplierObject|SupplierObject[]
     * @throws NoSupplier
     */
    public function getSupplier(
        string $supplier = '',
        bool $force_flush = false
    ) {
        if (empty($supplier)) {
            // Fetch all suppliers
            if ($force_flush || empty($this->supplierCache)) {
                $supplierCache = [];
                $allSuppliers = \Airship\list_all_files(ROOT . '/config/supplier_keys', 'json');
                foreach ($allSuppliers as $supplierKeyFile) {
                    // We want everything except the .json
                    $supplier = \substr($this->getEndPiece($supplierKeyFile), 0, -5);

                    $data = \Airship\loadJSON($supplierKeyFile);
                    $supplierCache[$supplier] = new SupplierObject($supplier, $data);
                }
                $this->supplierCache = $supplierCache;
            }
            return $this->supplierCache;
        }
        // Otherwise, we're just fetching one supplier's keys
        if ($force_flush || empty($this->supplierCache[$supplier])) {
            try {
                $data = \Airship\loadJSON(ROOT . '/config/supplier_keys/' . $supplier . '.json');
            } catch (FileNotFound $ex) {
                throw new NoSupplier($supplier, 0, $ex);
            }
            $this->supplierCache[$supplier] = new SupplierObject($supplier, $data);
        }
        if (isset($this->supplierCache[$supplier])) {
            return $this->supplierCache[$supplier];
        }
        throw new NoSupplier();
    }
}