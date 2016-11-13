<?php
declare(strict_types=1);
namespace Airship\Engine\Continuum;

use Airship\Alerts\Continuum\NoSupplier;
use Airship\Engine\{
    Continuum,
    Keyggdrasil,
    Keyggdrasil\Peer,
    State
};
use ParagonIE\Halite\Asymmetric\SignaturePublicKey;

/**
 * Class Channel
 *
 * Abstracts a lot of the Channel features away from other code
 *
 * @package Airship\Engine\Continuum
 */
class Channel
{
    /**
     * @var string
     */
    protected $name = '';

    /**
     * @var Continuum|Keyggdrasil
     */
    protected $parent;

    /**
     * @var Peer[]
     */
    protected $peers = [];

    /**
     * @var SignaturePublicKey
     */
    protected $publicKey;

    /**
     * @var string[]
     */
    protected $urls = [];

    /**
     * @var string
     */
    protected $ext = '.phar';

    /**
     * Channel constructor.
     *
     * @param object $parent (Continuum or Keyggdrasil)
     * @param string $name
     * @param array $config
     * @throws \TypeError
     */
    public function __construct($parent, string $name, array $config = [])
    {
        if ($parent instanceof Keyggdrasil || $parent instanceof Continuum) {
            $this->parent = $parent;
        }
        if (!\is1DArray($config['urls'])) {
            throw new \TypeError(
                \trk('errors.type.expected_1d_array')
            );
        }
        // The channel should be signing responses at the application layer:
        $this->publicKey = new SignaturePublicKey(
            \Sodium\hex2bin($config['publickey'])
        );
        $this->name = $name;
        foreach (\array_values($config['urls']) as $index => $url) {
            if (!\is_string($url)) {
                throw new \TypeError(
                    \trk('errors.type.expected_string_array', \gettype($url), $index)
                );
            }
            $this->urls[] = $url;
        }
    }
    /**
     * Create a new supplier.
     *
     * @param array $data
     * @return Supplier
     * @throws NoSupplier
     */
    public function createSupplier(array $data): Supplier
    {
        return $this->parent->createSupplier($this->name, $data);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return SignaturePublicKey
     */
    public function getPublicKey(): SignaturePublicKey
    {
        return $this->publicKey;
    }

    /**
     * Get all suppliers for a particular channel
     *
     * @return Supplier[]
     * @throws
     */
    public function getAllSuppliers(): array
    {
        return $this->parent->getSupplier();
    }

    /**
     * Get a supplier
     *
     * @param string $name
     * @param bool $flush
     * @return Supplier
     * @throws NoSupplier
     */
    public function getSupplier(string $name, bool $flush = false): Supplier
    {
        return $this->parent->getSupplier($name, $flush);
    }

    /**
     * Get all URLs
     *
     * By default, this shuffles them randomly.
     * If you're in tor-only mode, it prioritizes .onion domains first.
     *
     * @param bool $doNotShuffle
     * @return string[]
     */
    public function getAllURLs(bool $doNotShuffle = false): array
    {
        $state = State::instance();
        $candidates = [];
        if ($state->universal['tor-only']) {
            // Prioritize Tor Hidden Services
            $after = [];
            foreach ($this->urls as $url) {
                if (\Airship\isOnionUrl($url)) {
                    $candidates[] = $url;
                } else {
                    $after[] = $url;
                }
            }

            // Shuffle each array separately, to maintain priority;
            if (!$doNotShuffle) {
                \Airship\secure_shuffle($candidates);
                \Airship\secure_shuffle($after);
            }

            foreach ($after as $url) {
                $candidates[] = $url;
            }
        } else {
            // All URLs treated the same.
            $candidates = $this->urls;
            if (!$doNotShuffle) {
                \Airship\secure_shuffle($candidates);
            }
        }
        return $candidates;
    }

    /**
     * The natural log of the size of the peer list, rounded up.
     *
     * @param int $sizeOfList
     * @return int
     */
    public function getAppropriatePeerSize(int $sizeOfList = 0): int
    {
        if ($sizeOfList < 1) {
            $sizeOfList = \count($this->peers);
        }
        $log = (int) \ceil(
            \log($sizeOfList)
        );
        if ($log < 1) {
            return 1;
        }
        return $log;
    }

    /**
     * Get a list of peers for a given channel
     *
     * @param bool $forceFlush
     * @return Peer[]
     */
    public function getPeerList(bool $forceFlush = false): array
    {
        if (!empty($this->peers) && !$forceFlush) {
            return $this->peers;
        }
        $filePath = ROOT . '/config/channel_peers/' . $this->name . '.json';
        $peer = [];

        if (!\file_exists($filePath)) {
            \file_put_contents($filePath, '[]');
            return $peer;
        }

        $json = \Airship\loadJSON($filePath);
        foreach ($json as $data) {
            $peer[] = new Peer($data);
        }
        $this->peers = $peer;
        return $this->peers;
    }

    /**
     * Get a random URL
     *
     * @return string
     */
    public function getURL(): string
    {
        $state = State::instance();
        $candidates = [];
        if ($state->universal['tor-only']) {
            // Prioritize Tor Hidden Services
            foreach ($this->urls as $url) {
                if (\Airship\isOnionUrl($url)) {
                    $candidates[] = $url;
                }
            }
            // If we had any .onions, we will only use those.
            // Otherwise, use non-Tor URLs over Tor.
            if (empty($candidates)) {
                $candidates = $this->urls;
            }
        } else {
            $candidates = $this->urls;
        }
        $max = \count($candidates) - 1;
        return $candidates[\random_int(0, $max)];
    }
}
