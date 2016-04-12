<?php
declare(strict_types=1);
namespace Airship\Engine\Continuum;

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

    /**
     * UpdateInfo constructor.
     * @param array $json
     * @param string $channel
     * @param string $version
     */
    public function __construct(array $json, string $channel, string $version = '')
    {
        $this->response = $json;
        $this->channel = $channel;
        $this->checksum = $json['checksum'];
        $this->releaseInfo = $json['release_info'];
        if (empty($version)) {
            $this->version = $json['version'];
        } else {
            $this->version = $version;
        }
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
