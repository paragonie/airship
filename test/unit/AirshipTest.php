<?php

class AirshipTest extends PHPUnit_Framework_TestCase
{
    public function testOnionUrl()
    {
        $this->assertTrue(
            \Airship\isOnionUrl('http://duskgytldkxiuqc6.onion')
        );
        $this->assertTrue(
            \Airship\isOnionUrl('https://facebookcorewwwi.onion')
        );
        $this->assertTrue(
            \Airship\isOnionUrl('http://example.com.onion')
        );

        $this->assertFalse(
            \Airship\isOnionUrl('http://duskgytldkxiuqc6.onion.to')
        );
        $this->assertFalse(
            \Airship\isOnionUrl('https://duskgytldkxiuqc6.onion.to')
        );
        $this->assertFalse(
            \Airship\isOnionUrl('http://example.com?.onion')
        );
    }
}