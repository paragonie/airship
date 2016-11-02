<?php
declare(strict_types=1);
namespace Airship\Engine\Bolt;

use Airship\Alerts\{
    Continuum\CouldNotCreateSupplier,
    Continuum\NoSupplier,
    FileSystem\AccessDenied,
    FileSystem\FileNotFound
};
use Airship\Engine\Continuum\Supplier as SupplierObject;

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
     * @throws AccessDenied
     * @throws CouldNotCreateSupplier
     */
    public function createSupplier(string $channelName, array $data): SupplierObject
    {
        $supplierName = $data['supplier'];
        if (\file_exists(ROOT . '/config/supplier_keys/' . $supplierName . '.json')) {
            throw new CouldNotCreateSupplier(
                'File already exists: config/supplier_keys/' . $supplierName . '.json'
            );
        }
        $written = \file_put_contents(
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
        if ($written === false) {
            throw new AccessDenied(
                \__('Could not save new key')
            );
        }

        return $this->getSupplier($supplierName, true);
    }

    /**
     * For objects that don't bother caching
     *
     * @param string $supplier
     * @return SupplierObject
     * @throws AccessDenied
     * @throws FileNotFound
     * @throws NoSupplier
     */
    public function getSupplierDontCache(string $supplier): SupplierObject
    {
        if (!\file_exists(ROOT . '/config/supplier_keys/' . $supplier . '.json')) {
            throw new NoSupplier(
                \__(
                    "Supplier not found: %s", "default",
                    ROOT . '/config/supplier_keys/' . $supplier . '.json'
                )
            );
        }
        $data = \Airship\loadJSON(ROOT . '/config/supplier_keys/' . $supplier . '.json');
        return new SupplierObject($supplier, $data);
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
                $allSuppliers = \Airship\list_all_files(
                    ROOT . '/config/supplier_keys',
                    'json'
                );
                foreach ($allSuppliers as $supplierKeyFile) {
                    // We want everything except the .json
                    $supplier = \substr($this->getEndPiece($supplierKeyFile), 0, -5);
                    try {
                        $data = \Airship\loadJSON($supplierKeyFile);
                    } catch (FileNotFound $ex) {
                        $data = [];
                    }
                    $supplierCache[$supplier] = new SupplierObject($supplier, $data);
                }
                $this->supplierCache = $supplierCache;
            }
            return $this->supplierCache;
        }
        // Otherwise, we're just fetching one supplier's keys
        if ($force_flush || empty($this->supplierCache[$supplier])) {
            try {
                $supplierFile = ROOT . '/config/supplier_keys/' . $supplier . '.json';
                if (!\file_exists($supplierFile)) {
                    throw new NoSupplier(
                        \__(
                            "Supplier file not found: %s", "default",
                            $supplierFile
                        )
                    );
                }
                $data = \Airship\loadJSON($supplierFile);
            } catch (FileNotFound $ex) {
                throw new NoSupplier(
                    \__(
                        "Supplier not found: %s", "default",
                        $supplier
                    ),
                    0,
                    $ex
                );
            }
            $this->supplierCache[$supplier] = new SupplierObject($supplier, $data);
        }
        if (isset($this->supplierCache[$supplier])) {
            return $this->supplierCache[$supplier];
        }
        throw new NoSupplier();
    }
}
