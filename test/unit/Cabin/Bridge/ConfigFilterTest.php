<?php
declare(strict_types=1);
namespace Airship\UnitTests\Cabin\Bridge;

use Airship\Cabin\Bridge\ConfigFilter;

class ConfigFilterTest extends \PHPUnit_Framework_TestCase
{
    public function testHappyPath()
    {
        $filter = new ConfigFilter();
        $input = [
            'config_extra' => [
                'board' => [
                    'enabled' => 'on'
                ],
                'recaptcha' => [
                    'secret-key' => 'abc',
                    'site-key' => 'test'
                ],
                'password-reset' => [
                    'ttl' => '30'
                ],
                'file' => [
                    'cache' => '900'
                ]
            ],
            'twig_vars' => [
                'active-motif' => 'test-motif',
                'tagline' => 'test!',
                'title' => null
            ]
        ];
        $expectedOutput = [
            'config_extra' => [
                'board' => [
                    'enabled' => true],
                'file' => [
                    'cache' => 900
                ],
                'password-reset' => [
                    'enabled' => false,
                    'logout' => false,
                    'ttl' => 30
                ],
                'recaptcha' => [
                    'secret-key' => 'abc',
                    'site-key' => 'test'
                ],
                'two-factor' => [
                    'issuer' => '',
                    'label' => '',
                    'length' => 0,
                    'period' => 30
                ]
            ],
            'twig_vars' => [
                'active-motif' => 'test-motif',
                'tagline' => 'test!',
                'title' => ''
            ]
        ];
        $output = $filter($input);

        $this->assertSame(
            \json_encode($expectedOutput, JSON_PRETTY_PRINT),
            \json_encode($output, JSON_PRETTY_PRINT)
        );
        $this->assertEquals(
            $expectedOutput,
            $output
        );
    }
}
