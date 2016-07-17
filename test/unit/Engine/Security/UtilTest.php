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

    /**
     * @covers Util::downloadFileType()
     */
    public function testDownloadFileType()
    {
        $vectors = [
            [
                'in' =>
                    'text/javascript',
                'out' =>
                    'text/plain'
            ],
            [
                'in' =>
                    'image/png',
                'out' =>
                    'image/png'
            ],
            [
                'in' =>
                    'application/javascript',
                'out' =>
                    'text/plain'
            ],
            [
                'in' =>
                    'text/html',
                'out' =>
                    'text/plain'
            ],
            [
                'in' =>
                    'text/html; charset=UTF-8',
                'out' =>
                    'text/plain; charset=UTF-8'
            ]
        ];
        foreach ($vectors as $test) {
            $this->assertSame(
                $test['out'],
                Util::downloadFileType($test['in'])
            );
        }
    }
}
