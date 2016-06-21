<?php
declare(strict_types=1);
namespace Airship\UnitTests\Cabin\Hull;

use Airship\Cabin\Hull\ConfigFilter;

class ConfigFilterTest extends \PHPUnit_Framework_TestCase
{
    public function testHappyPath()
    {
        $filter = new ConfigFilter();
        $input = [
            'config_extra' => [
                'blog' => [
                    'cachelists' => '1',
                    'comments' => [
                        'depth_max' => 10,
                        'enabled' => 'on'
                    ],
                    'per_page' => 20
                ],
                'recaptcha' => [
                    'site-key' => null,
                    'secret-key' => 'boo'
                ],
                'file' => [
                    'cache' => 10800
                ]
            ],
            'twig_vars' => [
                'active-motif' => 'test-motif',
                'blog' => [
                    'title' => 'Test!'
                ],
                'tagline' => 'test!',
                'title' => null
            ]
        ];
        $expectedOutput = [
            'config_extra' => [
                'blog' => [
                    'cachelists' => true,
                    'comments' => [
                        'depth_max' => 10,
                        'enabled' => true,
                        'guests' => false,
                        'recaptcha' => false
                    ],
                    'per_page' => 20
                ],
                'homepage' => [
                    'blog-posts' => 5
                ],
                'file' => [
                    'cache' => 10800
                ],
                'recaptcha' => [
                    'secret-key' => 'boo',
                    'site-key' => ''
                ]
            ],
            'twig_vars' => [
                'active-motif' => 'test-motif',
                'blog' => [
                    'tagline' => '',
                    'title' => 'Test!'
                ],
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
