<?php
use Airship\Engine\Security\Util;

/**
 * @backupGlobals disabled
 */
class UtilTest extends PHPUnit_Framework_TestCase
{
    /**
     * @covers Util::randomString()
     */
    public function testRandomString()
    {
        $sample = [
            Util::randomString(),
            Util::randomString()
        ];
        
        $this->assertNotSame($sample[0], $sample[1]);
    }
}
