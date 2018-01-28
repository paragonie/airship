<?php
declare(strict_types=1);

namespace Airship\UnitTests;
use Airship\Engine\Hail;
use function Airship\get_var_type;
use function Airship\makeHTTPS;
use GuzzleHttp\Client;
use ParagonIE\ConstantTime\Binary;
use PHPUnit\Framework\TestCase;

/**
 * Class AirshipTest
 * @package Airship\UnitTests
 */
class AirshipTest extends TestCase
{

    /**
     * @covers \Airship\all_keys_exist()
     */
    public function testAllKeysExist()
    {
        $this->assertTrue(
            \Airship\all_keys_exist(
                ['a', 'b'],
                ['a' => 1, 'b' => 2, 'c' => 'three']
            ),
            'All keys should be found present.'
        );
        $this->assertTrue(
            \Airship\all_keys_exist(
                ['a', 'b', 'c'],
                ['a' => 1, 'b' => 2, 'c' => 'three']
            ),
            'All keys should be found present.'
        );
        $this->assertFalse(
            \Airship\all_keys_exist(
                ['a', 'd'],
                ['a' => 1, 'b' => 2, 'c' => 'three']
            ),
            'The key, d, should not have been present.'
        );
    }

    /**
     * @covers \Airship\array_from_http_query()
     */
    public function testArrayFromHttpQuery()
    {
        $this->assertSame(
            [],
            \Airship\array_from_http_query(''),
            'Empty string should return empty array.'
        );
        $this->assertSame(
            ['foo' => 'bar'],
            \Airship\array_from_http_query('foo=bar'),
            'foo=bar should become an array of size 1with key foo and element bar'
        );
        $this->assertSame(
            ['foo' => 'bar', 'apple' => '1'],
            \Airship\array_from_http_query('foo=bar&apple=1')
        );
        $this->assertSame(
            ['foo' => 'bar', 'apple' => ['1']],
            \Airship\array_from_http_query('foo=bar&apple[]=1'),
            'Arrays are not being respected'
        );
        $this->assertSame(
            ['foo' => 'bar', 'apple' => ['baz' => '1']],
            \Airship\array_from_http_query('foo=bar&apple[baz]=1'),
            'Arrays are not being parsed correctly'
        );
    }

    /**
     * @covers \Airship\array_multi_diff()
     */
    public function testArrayMultiDiff()
    {
        $old = [
            1 => [
                'read' => true,
                'write' => true,
                'execute' => true
            ],
            2 => [
                'read' => true,
                'write' => false,
                'execute' => true
            ],
            3 => [
                'read' => false,
                'write' => false,
                'execute' => false
            ]
        ];
        $new_1 = [
            1 => [
                'read' => true,
                'write' => true,
                'execute' => true
            ],
            3 => [
                'read' => true,
                'write' => false,
                'execute' => false
            ]
        ];
        $new_2 = [
            1 => [
                'read' => true,
                'write' => true,
                'execute' => true
            ],
            2 => [
                'read' => true,
                'write' => false,
                'execute' => true
            ],
            3 => [
                'read' => false,
                'write' => false,
                'execute' => false
            ],
            4 => [
                'read' => true,
                'write' => false,
                'execute' => false
            ]
        ];
        $this->assertSame(
            [
                1 => [],
                3 => [
                    'read' => true
                ]
            ],
            \Airship\array_multi_diff($new_1, $old)
        );
        $this->assertSame(
            [
                4 => [
                    'read' => true,
                    'write' => false,
                    'execute' => false
                ],
                1 => [],
                2 => [],
                3 => []
            ],
            \Airship\array_multi_diff($new_2, $old)
        );


        $this->assertSame(
            [
                2 => [
                    'read' => true,
                    'write' => false,
                    'execute' => true
                ],
                1 => [],
                3 => [
                    'read' => false
                ]
            ],
            \Airship\array_multi_diff($old, $new_1)
        );

        $this->assertSame(
            [
                1 => [],
                2 => [],
                3 => []
            ],
            \Airship\array_multi_diff($old, $new_2)
        );
    }

    /**
     * @covers \Airship\chunk()
     */
    public function testChunk()
    {
        $this->assertSame(
            ['foo', 'bar', 'baz'],
            \Airship\chunk('foo/bar/baz')
        );
        $this->assertSame(
            ['foo', 'bar', 'baz'],
            \Airship\chunk('/foo/bar/baz/')
        );
        $this->assertSame(
            ['foo/bar/baz'],
            \Airship\chunk('foo/bar/baz', '?')
        );
    }

    /**
     * @covers \Airship\expand_version()
     */
    public function testExpandVersion()
    {
        $this->assertSame(
            1000100,
            \Airship\expand_version('1.0.1')
        );
        $this->assertSame(
            1010100,
            \Airship\expand_version('1.1.1')
        );
        $this->assertSame(
            1100100,
            \Airship\expand_version('1.10.1')
        );
        $this->assertSame(
            1009900,
            \Airship\expand_version('1.0.99')
        );
    }

    /**
     * @covers \Airship\get_ancestors()
     */
    public function testGetAncestors()
    {
        $this->assertSame(
            [
                'Airship\\UnitTests\\AirshipTest',
                '\\PHPUnit\\Framework\\TestCase',
                '\\PHPUnit\\Framework\\Assert'
            ],
            \Airship\get_ancestors(\get_class($this))
        );
    }

    /**
     * @covers \Airship\get_caller_namespace()
     */
    public function testGetCallerNamespace()
    {
        $this->assertSame(
            __NAMESPACE__,
            \Airship\get_caller_namespace()
        );
    }

    /**
     * @covers \Airship\get_var_type()
     */
    public function testGetVarType()
    {
        $this->assertSame('void', get_var_type());
        $this->assertSame('null', get_var_type(null));
        $this->assertSame('bool', get_var_type(true));
        $this->assertSame('string', get_var_type('test'));
        $this->assertSame('int', get_var_type(PHP_INT_MAX));
        $this->assertSame('float', get_var_type(PHP_INT_MAX * 2));
        $this->assertSame('object (' . self::class . ')', get_var_type($this));
        $this->assertSame(
            'object (' . self::class . ', ["\\\\PHPUnit\\\\Framework\\\\TestCase","\\\\PHPUnit\\\\Framework\\\\Assert"])',
            get_var_type($this, true)
        );
        $hail = new Hail(new Client());
        $this->assertSame('object (Airship\Engine\Hail)', get_var_type($hail));
        $this->assertSame('object (Airship\Engine\Hail, -- no parents --)', get_var_type($hail, true));
    }

    public function testMakeHTTPS()
    {
        $this->assertSame('https://localhost', makeHTTPS('http://localhost'));
        $this->assertSame('wss://localhost:9001/test', makeHTTPS('ws://localhost:9001/test'));
    }

    /**
     * @covers \Airship\keySlice()
     */
    public function testKeySlice()
    {
        $array = [
            'a' => true,
            'b' => 12345,
            'c' => 'testing',
            'd' => 'delicious'
        ];

        $this->assertSame(
            ['a' => true],
            \Airship\keySlice($array, ['a'])
        );
        $this->assertSame(
            ['a' => true, 'c' => 'testing'],
            \Airship\keySlice($array, ['a', 'c'])
        );
        $this->assertSame(
            ['a' => true, 'c' => 'testing'],
            \Airship\keySlice($array, ['c', 'a'])
        );
    }

    /**
     * @covers \Airship\isOnionUrl()
     */
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


    /**
     * @covers \Airship\parseJSON()
     */
    public function testParseJSON()
    {
        $this->assertEquals(
            (object) ['a' => true],
            \Airship\parseJSON('{"a":true}')
        );
        $this->assertSame(
            ['a' => true],
            \Airship\parseJSON('{"a":true}', true)
        );
        $this->assertSame(
            ['a' => true],
            \Airship\parseJSON('{"a":true/*, "b": false */}', true)
        );
        $this->assertSame(
            ['a' => true],
            \Airship\parseJSON('
                {
                    "a":true
                    /*, "b": false */
                }',
                true
            )
        );
        $this->assertSame(
            ['a' => true],
            \Airship\parseJSON('
                {
                    "a":true
                    //, "b": false
                }',
                true
            )
        );
        $this->assertSame(
            ['a' => true, 'b' => false],
            \Airship\parseJSON('
                {
                    "a":true,
                    "b": false
                    //, "c": false
                }',
                true
            )
        );
    }

    /**
     * @covers \Airship\path_to_filename()
     */
    public function testPathToFilename()
    {
        $this->assertSame(
            'AirshipTest.php',
            \Airship\path_to_filename(__FILE__)
        );
        $this->assertSame(
            'AirshipTest',
            \Airship\path_to_filename(__FILE__, true)
        );
    }

    /**
     * @covers \Airship\secure_shuffle()
     */
    public function testSecureShuffle()
    {
        $array = [];
        for ($i = 0; $i < 512; ++$i) {
            $array[] = random_int(PHP_INT_MIN, PHP_INT_MAX);
        }
        $copy = \array_values($array);
        \Airship\secure_shuffle($copy);
        $this->assertNotSame($copy, $array, 'Shuffled array should not be identical to original.');
        $this->assertCount(512, $copy, 'Shuffled array is not the correct size.');
    }

    /**
     * @covers \Airship\slugFromTitle()
     */
    public function testSlugFromTitle()
    {
        $this->assertSame(
            'a-b',
            \Airship\slugFromTitle('A - B')
        );
        $this->assertSame(
            'using-encryption-and-authentication-correctly-for-php-developers',
            \Airship\slugFromTitle('Using Encryption and Authentication Correctly (for PHP developers)')
        );
        $this->assertSame(
            'using-encryption-and-authentication-correctly-for-php-developers',
            \Airship\slugFromTitle('--- --- -- - Using Encryption and Authentication Correctly (for PHP developers) --- --- --')
        );
        $this->assertSame(
            'implementing-secure-user-authentication-in-php-applications-with-long-term-persistence-login-with-remember-me-cookies',
            \Airship\slugFromTitle('Implementing Secure User Authentication in PHP Applications with Long-Term Persistence (Login with "Remember Me" Cookies)')
        );
    }

    /**
     * @covers \Airship\tempnam()
     */
    public function testTempNam()
    {
        $name = \Airship\tempnam();
        $name2 = \Airship\tempnam('foo-');
        $name3 = \Airship\tempnam('foo-', 'txt');

        $this->assertFileNotExists($name);
        $this->assertFileNotExists($name2);
        $this->assertFileNotExists($name3);
        $this->assertSame(
            'foo-',
            Binary::safeSubstr(\Airship\path_to_filename($name3), 0, 4)
        );
        $this->assertSame(
            '.txt',
            Binary::safeSubstr($name3, -4)
        );
    }

    /**
     * @covers \Airship\uniqueId()
     */
    public function testUniqueId()
    {
        $strings = [];
        for ($i = 0; $i < 1024; ++$i) {
            $strings []= \Airship\uniqueId(33);
        }
        $this->assertSame(
            $strings,
            \array_unique($strings),
            'Collision!'
        );

        for ($i = 18; $i < 30; ++$i) {
            $unique = \trim(\Airship\uniqueId($i), '=');
            $this->assertSame(
                $i,
                Binary::safeStrlen($unique)
            );
        }
    }
}
