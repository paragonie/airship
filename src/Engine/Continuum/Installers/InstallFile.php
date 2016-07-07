<?php
declare(strict_types=1);
namespace Airship\Engine\Continuum\Installers;

use Airship\Engine\Continuum\Supplier;
use ParagonIE\Halite\File;

/**
 * Class InstallFile
 *
 * All of the information pertinent to a file we are installing.
 *
 * @package Airship\Engine\Continuum\Installers
 */
class InstallFile
{
    /**
     * @var string
     */
    protected $hash;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var array
     */
    protected $releaseInfo;

    /**
     * @var int
     */
    protected $size;

    /**
     * @var Supplier
     */
    protected $supplier;

    /**
     * @var string
     */
    protected $version;

    /**
     * InstallFile constructor.
     *
     * @param Supplier $supplier
     * @param array $data
     */
    public function __construct(Supplier $supplier, array $data)
    {
        $this->hash = File::checksum($data['path']);
        $this->releaseInfo = $data['data']['releaseinfo'];
        $this->root = $data['data']['merkle_root'];
        $this->path = $data['path'];
        $this->size = (int) ($data['size'] ?? \filesize($data['path']));
        $this->supplier = $supplier;
        $this->version = $data['version'];
    }

    /**
     * Get the hex-encoded hash of the file contents
     *
     * @return string
     */
    public function getHash(): string
    {
        return $this->hash;
    }

    /**
     * Get the Merkle root that matches this version's release
     *
     * @return string
     */
    public function getMerkleRoot(): string
    {
        return $this->root;
    }

    /**
     * Get the name of the file
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get the hex-encoded signature for this file.
     *
     * @return string
     */
    public function getSignature(): string
    {
        return $this->releaseInfo['signature'];
    }

    /**
     * Get the size of the file
     *
     * @return int
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Get the version of a particular update file.
     *
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Does the given hash match the file?
     *
     * @param string $hash
     * @return bool
     */
    public function hashMatches(string $hash): bool
    {
        return \hash_equals($this->hash, $hash);
    }

    /**
     * Check that the signature is valid for this supplier's
     * public keys.
     *
     * @param bool $fastExit
     * @return bool
     */
    public function signatureIsValid(bool $fastExit = false): bool
    {
        $result = false;
        foreach ($this->supplier->getSigningKeys() as $key) {
            $result = $result || File::verify(
                $this->path,
                $key['key'],
                $this->releaseInfo['signature']
            );
            if ($result && $fastExit) {
                return true;
            }
        }
        return $result;
    }
}
