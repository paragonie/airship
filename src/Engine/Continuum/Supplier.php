<?php
declare(strict_types=1);
namespace Airship\Engine\Continuum;

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
     * @var SignaturePublicKey[]
     */
    private $signing_keys = [];

    /**
     * Supplier constructor.
     *
     * @param $name
     * @param array $data
     */
    public function __construct($name, array $data = [])
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
     * @return SignaturePublicKey[]
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
     * @return Supplier
     */
    public function reloadSigningKeys(array $data = []): self
    {
        if (empty($data)) {
            $data = \Airship\loadJSON(
                ROOT . '/config/supplier_keys/' . $this->name . '.json'
            );
        }
        if (isset($data['signing_keys'])) {
            $keys = [];
            foreach ($data['signing_keys'] as $sk) {
                $keys[] = [
                    'type' => $sk['type'],
                    'key' => new SignaturePublicKey(
                        new HiddenString(
                            \Sodium\hex2bin($sk['public_key'])
                        )
                    )
                ];
            }
            $this->signing_keys = $keys;
        }
        return $this;
    }
}
