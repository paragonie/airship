<?php
declare(strict_types=1);
namespace Airship\Engine\Cache;

use Airship\Alerts\Security\DataCorrupted;
use Airship\Engine\{
    Contract\CacheInterface,
    Security\Util,
    State
};
use ParagonIE\ConstantTime\Base64UrlSafe;
use ParagonIE\Halite\{
    Key,
    Symmetric\Crypto as Symmetric,
    Symmetric\AuthenticationKey,
    Util as CryptoUtil
};

/**
 * Class SharedMemory
 *
 * Store values in shared memory.
 *
 * @package Airship\Engine\Cache
 */
class SharedMemory implements CacheInterface
{
    /**
     * @var AuthenticationKey|null
     */
    protected $authKey = null;

    /**
     * @var string
     */
    protected $cacheKeyL;
    /**
     * @var string
     */
    protected $cacheKeyR;

    /**
     * @var string
     */
    protected $personalization = '';

    /**
     * SharedMemory constructor
     *.
     * @param Key|null $cacheKey
     * @param AuthenticationKey|null $authKey
     * @param string $personalization
     */
    public function __construct(
        Key $cacheKey = null,
        AuthenticationKey $authKey = null,
        string $personalization = ''
    ) {
        if (!$cacheKey) {
            $state = State::instance();
            $cacheKey = $state->keyring['cache.hash_key'];
        }

        // We need a short hash key:
        $this->cacheKeyL = CryptoUtil::safeSubstr(
            $cacheKey->getRawKeyMaterial(),
            0,
            \SODIUM_CRYPTO_SHORTHASH_KEYBYTES
        );
        $this->cacheKeyR = CryptoUtil::safeSubstr(
            $cacheKey->getRawKeyMaterial(),
            \SODIUM_CRYPTO_SHORTHASH_KEYBYTES,
            \SODIUM_CRYPTO_SHORTHASH_KEYBYTES
        );

        if ($authKey) {
            $this->authKey = $authKey;
        }
        $this->personalization = $personalization;
    }

    /**
     * Get a cache entry
     *
     * @param string $key
     * @return null|mixed
     * @throws DataCorrupted
     */
    public function get(string $key)
    {
        $shmKey = $this->getSHMKey($key);
        if (!\apcu_exists($shmKey)) {
            return null;
        }
        $data = \apcu_fetch($shmKey);

        if ($this->authKey) {
            // We're authenticating this value:
            $mac = Util::subString($data, 0, \SODIUM_CRYPTO_GENERICHASH_BYTES_MAX);
            $data = Util::subString($data, \SODIUM_CRYPTO_GENERICHASH_BYTES_MAX);
            if (!Symmetric::verify($data, $this->authKey, $mac, true)) {
                // Someone messed with our shared memory.
                throw new DataCorrupted();
            }
        }
        return \json_decode($data, true);
    }

    /**
     * Set a cache entry
     *
     * @param string $key
     * @param $value
     * @return bool
     */
    public function set(string $key, $value): bool
    {
        // We will NOT use unserialize here.
        $value = \json_encode($value);
        if (!$value) {
            return false;
        }
        if ($this->authKey) {
            // We're authenticating this value:
            $mac = Symmetric::authenticate($value, $this->authKey, true);
            $value = $mac . $value;
        }
        $shmKey = $this->getSHMKey($key);
        return \apcu_add($shmKey, $value);
    }

    /**
     * Delete a cache entry
     *
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool
    {
        $shmKey = $this->getSHMKey($key);
        if (!\apcu_exists($shmKey)) {
            return true;
        }
        // Fetch
        $fetch = \apcu_fetch($shmKey);
        $length = Util::stringLength($fetch);
        // Wipe:
        \sodium_memzero($fetch);
        \apcu_store(
            $shmKey,
            \str_repeat("\0", $length)
        );
        // Delete
        return \apcu_delete($shmKey);
    }

    /**
     * Add a prefix to the hash function input
     *
     * @param string $string
     * @return self
     */
    public function personalize(string $string): self
    {
        $this->personalization = $string;
        return $this;
    }

    /**
     * Compute an integer key for shared memory
     *
     * @param string $lookup
     * @return string
     */
    public function getSHMKey(string $lookup): string
    {
        return Base64UrlSafe::encode(
            \sodium_crypto_shorthash(
                $this->personalization . $lookup,
                $this->cacheKeyL
            ) .
            \sodium_crypto_shorthash(
                $this->personalization . $lookup,
                $this->cacheKeyR
            )
        );
    }
}
