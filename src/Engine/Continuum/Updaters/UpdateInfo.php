<?php
declare(strict_types=1);
namespace Airship\Engine\Continuum\Updaters;

use \ParagonIE\Halite\Asymmetric\SignaturePublicKey;

/**
 * Class UpdateInfo
 * @package Airship\Engine\Continuum
 */
class UpdateInfo
{
    protected $channel;
    protected $releaseInfo;
    protected $checksum;
    protected $response;
    protected $merkleRoot;

    protected $supplierName;
    protected $packageName;

    /**
     * UpdateInfo constructor.
     * @param array $json
     * @param string $channelURL
     * @param SignaturePublicKey $channelPublicKey
     */
    public function __construct(
        array $json,
        string $channelURL,
        SignaturePublicKey $channelPublicKey,
        string $supplierName,
        string $packageName
    ) {
        $this->response = $json;
        $this->channel = $channelURL;
        $this->publicKey = $channelPublicKey;
        $this->checksum = $json['checksum'];
        $this->releaseInfo = $json['releaseinfo'];
        $this->version = $json['version'];
        $this->merkleRoot = $json['merkle_root'];
        $this->supplierName = $supplierName;
        $this->packageName = $packageName;
    }

    /**
     * Get the channel that we retrieved this update from.
     *
     * @return string
     */
    public function getChannel(): string
    {
        return $this->channel;
    }

    /**
     * Get the channel's public key
     *
     * @return SignaturePublicKey
     */
    public function getChannelPublicKey(): SignaturePublicKey
    {
        return $this->publicKey;
    }

    /**
     * Get the checksum of the file.
     *
     * @return string
     */
    public function getChecksum(): string
    {
        return $this->checksum;
    }

    /**
     * Get the expected Merkle root (verify with Keyggdrasil's cache)
     *
     * @param bool $raw
     * @return string
     */
    public function getMerkleRoot(bool $raw = false): string
    {
        if ($raw) {
            return \Sodium\hex2bin($this->merkleRoot);
        }
        return $this->merkleRoot;
    }

    /**
     * Get the name of the package we're updating
     *
     * @return string
     */
    public function getPackageName(): string
    {
        return $this->packageName;
    }

    /**
     * Get the signature
     *
     * @param bool $hex
     * @return string
     */
    public function getSignature(bool $hex = false): string
    {
        $signature = $this->releaseInfo['signature'];
        if (!$hex) {
            return \Sodium\hex2bin($signature);
        }
        return $signature;
    }

    /**
     * Get the supplier's name
     *
     * @return string
     */
    public function getSupplierName(): string
    {
        return $this->supplierName;
    }

    /**
     * Get the full response body
     *
     * @return array
     */
    public function getResponse(): array
    {
        return $this->response;
    }

    /**
     * Get the version for this particular update
     *
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }


    /**
     * Get the expanded version for this particular update
     *
     * @return int
     */
    public function getVersionExpanded(): int
    {
        return \Airship\expand_version($this->version);
    }
}
