<?php
declare(strict_types=1);
namespace Airship\Engine\Keyggdrasil;

use Airship\Engine\State;
use ParagonIE\ConstantTime\Base64UrlSafe;
use ParagonIE\Halite\Asymmetric\SignaturePublicKey;

/**
 * Class Peer
 *
 * Represents a peer for a given channel
 *
 * @package Airship\Engine\Keyggdrasil
 */
class Peer
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var bool
     */
    private $onion = false;

    /**
     * @var SignaturePublicKey
     */
    private $publicKey;

    /**
     * @var string[]
     */
    private $urls = [];

    /**
     * Peer constructor.
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->name = $config['name'];
        $this->publicKey = new SignaturePublicKey(
            Base64UrlSafe::decode($config['public_key'])
        );
        $this->urls = $config['urls'];
        foreach ($this->urls as $url) {
            if (\Airship\isOnionUrl($url)) {
                $this->onion = true;
                break;
            }
        }
    }

    /**
     * Does this domain have a .onion address?
     *
     * @return bool
     */
    public function hasOnionAddress(): bool
    {
        return $this->onion;
    }

    /**
     * Get all URLs
     *
     * @param string $suffix
     * @param bool $doNotShuffle
     * @return string[]
     */
    public function getAllURLs(string $suffix = '', bool $doNotShuffle = false): array
    {
        $state = State::instance();
        $candidates = [];
        if ($state->universal['tor-only']) {
            // Prioritize Tor Hidden Services
            $after = [];
            foreach ($this->urls as $url) {
                if (\Airship\isOnionUrl($url)) {
                    $candidates[] = $url . $suffix;
                } else {
                    $after[] = $url . $suffix;
                }
            }

            if (!$doNotShuffle) {
                \Airship\secure_shuffle($candidates);
                \Airship\secure_shuffle($after);
            }

            foreach ($after as $url) {
                $candidates[] = $url . $suffix;
            }
        } else {
            $candidates = $this->urls;
            if (!$doNotShuffle) {
                \Airship\secure_shuffle($candidates);
            }
            foreach (\array_keys($candidates) as $i) {
                $candidates[$i] .= $suffix;
            }
        }
        return $candidates;
    }

    /**
     * Get the Ed25519 public key for this peer
     *
     * @return SignaturePublicKey
     */
    public function getPublicKey(): SignaturePublicKey
    {
        return $this->publicKey;
    }
}
