<?php
declare(strict_types=1);
use \Airship\Engine\Security\HiddenString;
use \ParagonIE\ConstantTime\Base64UrlSafe;

/**
 * @backupGlobals disabled
 * @covers HiddenString
 */
class HiddenStringTest extends PHPUnit_Framework_TestCase
{
    public function testRandomString()
    {
        $str = Base64UrlSafe::encode(\random_bytes(32));
        $hidden = new HiddenString($str);

        ob_start(); var_dump($hidden);
        $dump = ob_get_clean();
        $this->assertFalse(\strpos($dump, $str));

        $print = \print_r($hidden, true);
        $this->assertFalse(\strpos($print, $str));

        $cast = (string) $hidden;
        $this->assertFalse(\strpos($cast, $str));
    }
}
