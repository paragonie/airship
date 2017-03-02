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
use ParagonIE\ConstantTime\Binary;

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
     * @throws \TypeError
     */
    public function createSupplier(string $channelName, array $data): SupplierObject
    {
        $supplierName = $this->escapeSupplierName($data['supplier']);
        if (\file_exists(ROOT . '/config/supplier_keys/' . $supplierName . '.json')) {
            throw new CouldNotCreateSupplier(
                'File already exists: config/supplier_keys/' . $supplierName . '.json'
            );
        }
        /**
         * @var int
         */
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
        if (!\is_int($written)) {
            throw new AccessDenied(
                \__('Could not save new key')
            );
        }

        /**
         * @var SupplierObject
         */
        $supplier = $this->getSupplier($supplierName, true);
        if (!\is_object($supplier)) {
            throw new \TypeError('Expected a single supplier, got multiple');
        }
        return $supplier;
    }

    /**
     * @param string $supplier
     * @return string
     */
    public function escapeSupplierName(string $supplier): string
    {
        return \preg_replace(
            '#[^A-Za-z0-9\-\_]#',
            '',
            $supplier
        );
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
        $supplier = $this->escapeSupplierName($supplier);
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
     * @return SupplierObject|array<mixed, SupplierObject>
     * @throws NoSupplier
     */
    public function getSupplier(
        string $supplier = '',
        bool $force_flush = false
    ) {
        if (empty($supplier)) {
            // Fetch all suppliers
            if ($force_flush || empty($this->supplierCache)) {
                /**
                 * @var array<string, SupplierObject>
                 */
                $supplierCache = [];
                $allSuppliers = \Airship\list_all_files(
                    ROOT . '/config/supplier_keys',
                    'json'
                );
                foreach ($allSuppliers as $supplierKeyFile) {
                    // We want everything except the .json
                    $supplier = $this->escapeSupplierName(
                        Binary::safeSubstr(
                            $this->getEndPiece($supplierKeyFile),
                            0,
                            -5
                        )
                    );
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
                $supplier = $this->escapeSupplierName($supplier);
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


    /**
     * Get the last piece of a path
     *
     * @param string $fullPath
     * @return string
     */
    public function getEndPiece(string $fullPath): string
    {
        $trimmedPath = \trim($fullPath. '/');
        $arr = \explode('/', $trimmedPath);
        return (string) \array_pop($arr);
    }
}
