<?php
declare(strict_types=1);
use \Airship\Engine\Security\AirBrake;

require_once \dirname(__DIR__).'/MockDatabase.php';

/**
 * @backupGlobals disabled
 * @covers AirBrake
 */
class AirBrakeTest extends PHPUnit_Framework_TestCase
{
    public function testCutoff()
    {
        $airbrake = new AirBrake(new \MockDatabase());

        // precisely 31 second shy of 12 hours
        $cutoff = $airbrake->getCutoff(43169);
            $this->assertSame(0, $cutoff->y);
            $this->assertSame(0, $cutoff->m);
            $this->assertSame(0, $cutoff->d);
            $this->assertSame(11, $cutoff->h);
            $this->assertSame(59, $cutoff->i);
            $this->assertSame(29, $cutoff->s);

        $cutoff = $airbrake->getCutoff(86400);
            $this->assertSame(0, $cutoff->y);
            $this->assertSame(0, $cutoff->m);
            $this->assertSame(1, $cutoff->d);
            $this->assertSame(0, $cutoff->h);
            $this->assertSame(0, $cutoff->i);
            // Leap second tolerance:
            $this->assertLessThan(2, $cutoff->s);
    }

    /**
     * @covers AirBrake::getSubnet
     * @covers AirBrake::getIPv4Subnet
     * @covers AirBrake::getIPv6Subnet
     */
    public function testSubnet()
    {
        $airbrake = new AirBrake(new \MockDatabase());

        // IPv4
        $this->assertSame(
            '64.233.191.254/32',
            $airbrake->getIPv4Subnet('64.233.191.254', 32)
        );
        $this->assertSame(
            '64.233.191.252/30',
            $airbrake->getIPv4Subnet('64.233.191.254', 30)
        );
        $this->assertSame(
            '64.233.191.240/28',
            $airbrake->getIPv4Subnet('64.233.191.254', 28)
        );
        $this->assertSame(
            '64.233.191.0/24',
            $airbrake->getIPv4Subnet('64.233.191.254', 24)
        );
        $this->assertSame(
            '64.233.188.0/22',
            $airbrake->getIPv4Subnet('64.233.191.254', 22)
        );

        // IPv6
        $this->assertSame(
            '2001:db8:85a3::8a2e:370:7334/127',
            $airbrake->getIPv6Subnet('2001:0db8:85a3:0000:0000:8a2e:0370:7334', 127)
        );
        $this->assertSame(
            '2001:db8:85a3::8a2e:370:7300/120',
            $airbrake->getIPv6Subnet('2001:0db8:85a3:0000:0000:8a2e:0370:7300', 120)
        );
        $this->assertSame(
            '2001:db8:85a3::/64',
            $airbrake->getIPv6Subnet('2001:0db8:85a3:0000:0000:8a2e:0370:7334', 64)
        );
        $this->assertSame(
            '2001:db8:85a3::/48',
            $airbrake->getIPv6Subnet('2001:0db8:85a3:0000:0000:8a2e:0370:7334', 48)
        );
        $this->assertSame(
            '2001:db8:8500::/40',
            $airbrake->getIPv6Subnet('2001:0db8:85a3:0000:0000:8a2e:0370:7334', 40)
        );
    }
}
