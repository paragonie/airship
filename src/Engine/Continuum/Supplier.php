<?php
declare(strict_types=1);
namespace Airship\Engine\Continuum;

use \ParagonIE\Halite\Asymmetric\SignaturePublicKey;

/**
 * Class Supplier
 *
 * This abstracts away a particular supplier.
 *
 * @package Airship\Engine\Continuum
 */
class Supplier
{
    private $name;
    private $channels;
    private $signing_keys = [];

    /**
     * Supplier constructor.
     * @param $name
     * @param array $data
     */
    public function __construct($name, array $data = [])
    {
        $this->name = $name;
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
     * @return array ('key' => SignaturePublicKey
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
     * @return Supplier
     */
    public function reloadSigningKeys(array $data = []): self
    {
        if (empty($data)) {
            $data = \Airship\loadJSON(
                ROOT . '/config/signing_keys/' . $this->name . '.json'
            );
        }
        if (isset($data['signing_keys'])) {
            $keys = [];
            foreach ($data['signing_keys'] as $sk) {
                $keys[] = [
                    'type' => $sk['type'],
                    'key' => new SignaturePublicKey(\Sodium\hex2bin($sk['public_key']), true, true, true),
                    'from' => empty($sk['validity']['from'])
                        ? null
                        : new \DateTime($sk['validity']['from']),
                    'until' => empty($sk['validity']['until'])
                        ? null
                        : new \DateTime($sk['validity']['until'])
                ];
            }
            $this->signing_keys = $keys;
        }
        return $this;
    }
}
