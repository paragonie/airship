<?php
declare(strict_types=1);
namespace Airship\Engine\Contract;

/**
 * Interface CacheInterface
 * @package Airship\Engine\Contract
 */
interface CacheInterface
{
    /**
     * Delete a cache entry
     *
     * @param string $key
     */
    public function delete(string $key);

    /**
     * Get a cache entry
     *
     * @param string $key
     * @return null|mixed
     */
    public function get(string $key);

    /**
     * Set a cache entry
     *
     * @param string $key
     * @param $value
     * @return mixed
     */
    public function set(string $key, $value);
}
