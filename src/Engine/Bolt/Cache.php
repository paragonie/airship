<?php
declare(strict_types=1);
namespace Airship\Engine\Bolt;

use Airship\Engine\Cache\{
    File,
    SharedMemory
};
use Airship\Engine\Contract\CacheInterface;

/**
 * Trait Cache
 *
 * Used to cache stuff in memory or the filesystem.
 *
 * @package Airship\Engine\Bolt
 */
trait Cache
{
    /**
     * @var CacheInterface
     */
    public $airship_filecache_object;

    /**
     * @var CacheInterface
     */
    public $airship_cspcache_object;

    /**
     * After loading the Cache bolt in place, configure it.
     *
     * @return void
     */
    function tightenCacheBolt(): void
    {
        static $tightened = false;
        if ($tightened) {
            return;
        }
        if (\extension_loaded('apcu')) {
            $this->airship_filecache_object = (new SharedMemory())
                ->personalize('staticPage:');
            $this->airship_cspcache_object = (new SharedMemory())
                ->personalize('contentSecurityPolicy:');
        } else {
            $this->airship_filecache_object = new File(
                ROOT . '/tmp/cache/static'
            );
            $this->airship_cspcache_object = new File(
                ROOT . '/tmp/cache/csp_static'
            );
        }
    }
}
