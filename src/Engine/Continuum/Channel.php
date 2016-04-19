<?php
declare(strict_types=1);
namespace Airship\Engine\Continuum;

use \Airship\Engine\State;

/**
 * Class Channel
 *
 * Abstracts a lot of the Channel features away from other code
 *
 * @package Airship\Engine\Continuum
 */
class Channel
{
    protected $name = '';
    protected $peers = [];
    protected $urls = [];

    /**
     * Channel constructor.
     *
     * @todo \trk() these exception messages
     *
     * @param string $name
     * @param string[] $urls
     * @throws \TypeError
     */
    public function __construct(string $name, array $urls = [])
    {
        if (!\is1DArray($urls)) {
            throw new \TypeError(
                'Expected a 1-dimensional array of strings'
            );
        }
        $this->name = $name;
        foreach (\array_values($urls) as $index => $url) {
            if (\is_string($url)) {
                throw new \TypeError(
                    'Expected an array of strings; ' . \gettype($url) . ' given at index ' . $index .'.'
                );
            }
            $this->urls[] = $url;
        }
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get all URLs
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
                if (\strpos($url, '.onion') !== false) {
                    $candidates[] = $url;
                } else {
                    $after[] = $url;
                }
            }

            if (!$doNotShuffle) {
                \Airship\secure_shuffle($candidates);
                \Airship\secure_shuffle($after);
            }

            foreach ($after as $url) {
                $candidates[] = $url;
            }
        } else {
            $candidates = $this->urls;
            if (!$doNotShuffle) {
                \Airship\secure_shuffle($candidates);
            }
        }
        return $candidates;
    }

    /**
     * Get a list of peers for a given channel
     *
     * @param bool $forceFlush
     * @return Peer[]
     */
    protected function getPeerList(bool $forceFlush = false): array
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
                if (\strpos($url, '.onion') !== false) {
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
