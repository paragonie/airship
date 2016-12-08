<?php
declare(strict_types=1);


use Airship\Alerts\Security\DataCorrupted;
use Airship\Engine\{
    Cache\SharedMemory,
    Contract\CacheInterface,
    Security\Util
};
use ParagonIE\ConstantTime\Base64UrlSafe;
use ParagonIE\Halite\{
    HiddenString,
    Symmetric\AuthenticationKey
};

/**
 * Class SharedMemoryTest
 * @backupGlobals disabled
 */
class SharedMemoryTest extends PHPUnit_Framework_TestCase
{
    /**
     * @covers SharedMemory::get()
     * @covers SharedMemory::set()
     */
    public function testGetSet()
    {
        if (!\extension_loaded('apcu')) {
            $this->markTestSkipped(
                'APCu is not installed'
            );
        }

        $cache = $this->getAdapter();
        $data = Base64UrlSafe::encode(\random_bytes(16));

        $this->assertTrue(
            $cache->set('unit-test-001', $data)
        );

        $read = $cache->get('unit-test-001');
        $this->assertSame(
            $data,
            $read
        );
    }

    /**
     * @covers SharedMemory::delete()
     */
    public function testDelete()
    {
        if (!\extension_loaded('apcu')) {
            $this->markTestSkipped(
                'APCu is not installed'
            );
        }

        $cache = $this->getAdapter();

        $data = Base64UrlSafe::encode(\random_bytes(16));

        $this->assertTrue(
            $cache->set('unit-test-002', $data)
        );

        $this->assertTrue(
            $cache->delete('unit-test-002')
        );

        $this->assertNull(
            $cache->get('unit-test-002')
        );
    }

    /**
     * @covers SharedMemory::personalize()
     */
    public function testPersonalSHMKey()
    {
        if (!\extension_loaded('apcu')) {
            $this->markTestSkipped(
                'APCu is not installed'
            );
        }
        $cache = $this->getAdapter()->personalize('unit test:');
        $this->assertSame('-h7RIRHuVi_6HtEhEe5WLw==', $cache->getSHMKey('apple'));
    }

        /**
     * @covers SharedMemory::getSHMKey()
     */
    public function testSHMKey()
    {
        $cacheA = $this->getAdapter();
        $cacheB = $this->getWeakAdapter();

        $this->assertSame('aUJfPrJYEWJpQl8-slgRYg==', $cacheA->getSHMKey('apple'));
        $this->assertNotSame($cacheA->getSHMKey('apple'), $cacheB->getSHMKey('apple'));

        $cacheC = $this->getRandomAdapter();
        $this->assertNotSame($cacheA->getSHMKey('apple'), $cacheC->getSHMKey('apple'));
        $this->assertNotSame($cacheB->getSHMKey('apple'), $cacheC->getSHMKey('apple'));
    }

    /**
     * Get the common adapter
     *
     * @return SharedMemory
     */
    private function getAdapter(): SharedMemory
    {
        $cacheKey = new AuthenticationKey(new HiddenString(\str_repeat("\x80", 32)));
        $authKey = new AuthenticationKey(new HiddenString(\str_repeat("\x7F", 32)));

        return new SharedMemory($cacheKey, $authKey);
    }

    /**
     * Get the common adapter
     *
     * @return SharedMemory
     */
    private function getRandomAdapter(): SharedMemory
    {
        $cacheKey = new AuthenticationKey(new HiddenString(\random_bytes(32)));

        return new SharedMemory($cacheKey);
    }

    /**
     * Get the common adapter
     *
     * @return SharedMemory
     */
    private function getWeakAdapter(): SharedMemory
    {
        $cacheKey = new AuthenticationKey(new HiddenString(\str_repeat("\x00", 32)));

        return new SharedMemory($cacheKey);
    }
}
