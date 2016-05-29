<?php

use Airship\Engine\Continuum\Version;

/**
 * @backupGlobals disabled
 */
class VersionTest extends PHPUnit_Framework_TestCase
{
    /**
     * Just some versions
     * 
     * @return array
     */
    public function demoVersions() : array
    {
        return [
            '1.0.0' => new Version('1.0.0'),
            '1.0.1' => new Version('1.0.1'),
            '1.0.2' => new Version('1.0.2'),
            '1.1.0' => new Version('1.1.0'),
            '1.1.1' => new Version('1.1.1'),
            '1.1.2' => new Version('1.1.2'),
            '1.2.0' => new Version('1.2.0'),
            '2.0.0' => new Version('2.0.0'),
            '2.0.0-rc1' => new Version('2.0.0-rc1')
        ];
    }
    
    /**
     * Basic logic test. Please don't break this!
     */
    public function testBasicLogic()
    {
        $versions = $this->demoVersions();
        
        // Some bad cases:
        $this->assertFalse($versions['1.0.0']->isUpgrade('1.0.0'));
        $this->assertFalse($versions['1.1.0']->isUpgrade('1.0.1'));
        $this->assertFalse($versions['2.0.0']->isUpgrade('1.0.1'));
        $this->assertFalse($versions['2.0.0']->isUpgrade('1.1.0'));
        $this->assertFalse($versions['2.0.0']->isUpgrade('1.1.1'));
        
        // These should all be consider an upgrade, of some form:
        $this->assertTrue($versions['1.0.0']->isUpgrade('1.0.1'));
        $this->assertTrue($versions['1.0.0']->isUpgrade('1.1.0'));
        $this->assertTrue($versions['1.0.0']->isUpgrade('2.0.0'));
        $this->assertTrue($versions['1.1.0']->isUpgrade('1.1.1'));
        $this->assertTrue($versions['1.1.0']->isUpgrade('1.2.0'));
        $this->assertTrue($versions['1.1.0']->isUpgrade('2.0.0'));
    }
    
    /**
     *  This is mostly to catch regressions.
     */
    public function testGroupArithmetic()
    {
        $t = \Airship\expand_version('3.45.67');
        
        $this->assertEquals(
            Version::getGroup($t, Version::GROUP_MAJOR),
            3
        );
        $this->assertEquals(
            Version::getGroup($t, Version::GROUP_MINOR),
            45
        );
        $this->assertEquals(
            Version::getGroup($t, Version::GROUP_PATCH),
            67
        );
        
        // Let's make sure large major versions don't break
        $u = \Airship\expand_version('125.0.0');
        $this->assertEquals(
            Version::getGroup($u, Version::GROUP_MAJOR),
            125
        );
        $this->assertEquals(
            Version::getGroup($u, Version::GROUP_MINOR),
            0
        );
        $this->assertEquals(
            Version::getGroup($u, Version::GROUP_PATCH),
            0
        );
    }
    
    /**
     * Test more granular test than the basic test above
     */
    public function testGranularLogic()
    {
        $versions = $this->demoVersions();
        
        // PATCH VERSIONS
        $this->assertFalse($versions['1.0.1']->isPatchUpgrade('1.0.0'));
        $this->assertFalse($versions['1.0.0']->isPatchUpgrade('1.0.0-rc1'));
        $this->assertFalse($versions['1.0.0']->isPatchUpgrade('1.1.0'));
        $this->assertFalse($versions['1.0.0']->isPatchUpgrade('1.1.1'));
        $this->assertFalse($versions['1.0.0']->isPatchUpgrade('2.0.0'));
        $this->assertFalse($versions['1.0.0']->isPatchUpgrade('2.0.1'));
        $this->assertFalse($versions['1.0.0']->isPatchUpgrade('2.1.0'));
        $this->assertFalse($versions['1.0.0']->isPatchUpgrade('2.1.1'));
        $this->assertFalse($versions['1.1.0']->isPatchUpgrade('2.0.0'));
        $this->assertFalse($versions['1.1.0']->isPatchUpgrade('2.1.0'));
        $this->assertFalse($versions['1.1.0']->isPatchUpgrade('2.1.1'));
        
        $this->assertTrue($versions['1.0.0']->isPatchUpgrade('1.0.1'));
        
        // MINOR VERSIONS
        $this->assertFalse($versions['1.1.0']->isMinorUpgrade('1.0.0'));
        $this->assertFalse($versions['1.0.0']->isMinorUpgrade('1.0.1'));
        $this->assertFalse($versions['1.0.0']->isMinorUpgrade('2.0.0'));
        $this->assertFalse($versions['1.0.0']->isMinorUpgrade('2.1.0'));
        
        $this->assertTrue($versions['1.0.0']->isMinorUpgrade('1.1.0'));
        $this->assertTrue($versions['1.0.0']->isMinorUpgrade('1.1.1'));
        $this->assertTrue($versions['1.0.0']->isMinorUpgrade('1.2.0'));
        $this->assertTrue($versions['1.1.0']->isMinorUpgrade('1.2.0'));
        
        // MAJOR VERSIONS
        $this->assertFalse($versions['2.0.0']->isMajorUpgrade('1.0.0'));
        $this->assertFalse($versions['1.0.0']->isMajorUpgrade('1.0.1'));
        $this->assertFalse($versions['1.0.0']->isMajorUpgrade('1.1.0'));
        
        $this->assertTrue($versions['1.0.0']->isMajorUpgrade('2.0.0'));
        $this->assertTrue($versions['1.0.0']->isMajorUpgrade('2.1.0'));
    }

    public function testUpgrade()
    {
        $versions = $this->demoVersions();
        $this->assertSame(
            '1.1.3',
            $versions['1.1.2']->getUpgrade(Version::GROUP_PATCH)
        );
        $this->assertSame(
            '1.2.0',
            $versions['1.1.2']->getUpgrade(Version::GROUP_MINOR)
        );
        $this->assertSame(
            '2.0.0',
            $versions['1.1.2']->getUpgrade(Version::GROUP_MAJOR)
        );
    }
}
