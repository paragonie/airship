<?php
declare(strict_types=1);
namespace Airship\Engine\Continuum;

use ParagonIE\ConstantTime\Hex;
use ParagonIE\Halite\Asymmetric\SignaturePublicKey;
use ParagonIE\Halite\HiddenString;

/**
 * Class Supplier
 *
 * This abstracts away a particular supplier.
 *
 * @package Airship\Engine\Continuum
 */
class Supplier
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var array
     */
    private $channels;

    /**
     * @var array<int, array<string, mixed>>
     */
    private $signing_keys = [];

    /**
     * Supplier constructor.
     *
     * @param string $name
     * @param array $data
     */
    public function __construct(string $name, array $data = [])
    {
        // Do not allow invalid characters in the name:
        $this->name = \preg_replace(
            '#[^0-9A-Za-z\-\_]#',
            '',
            $name
        );
        $this->channels = isset($data['channels'])
            ? $data['channels']
            : [];
        $this->reloadSigningKeys($data);
    }

    /**
     * Get an array SignaturePublicKey objects
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get an array SignaturePublicKey objects
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSigningKeys(): array
    {
        return $this->signing_keys;
    }

    /**
     * Get all of the channels
     *
     * @return array
     */
    public function getChannels(): array
    {
        return $this->channels ?? [];
    }

    /**
     * Reload the signing keys
     *
     * @param array $data
     * @return self
     */
    public function reloadSigningKeys(array $data = []): self
    {
        if (empty($data)) {
            $data = \Airship\loadJSON(
                ROOT . '/config/supplier_keys/' . $this->name . '.json'
            );
        }
        if (isset($data['signing_keys'])) {
            /**
             * @var array<int, array<string, mixed>>
             */
            $keys = [];
            foreach ($data['signing_keys'] as $sk) {
                $keys[] = [
                    'type' => $sk['type'],
                    'key' => new SignaturePublicKey(
                        new HiddenString(
                            Hex::decode($sk['public_key'])
                        )
                    )
                ];
            }
            $this->signing_keys = $keys;
        }
        return $this;
    }
}
