<?php

use Airship\Engine\Security\Filter\{
    BoolFilter,
    BoolArrayFilter,
    FloatFilter,
    FloatArrayFilter,
    GeneralFilterContainer,
    IntFilter,
    IntArrayFilter,
    StringFilter,
    StringArrayFilter
};

/**
 * @backupGlobals disabled
 */
class FilterTest extends PHPUnit_Framework_TestCase
{
    /**
     * @covers BoolFilter
     */
    public function testBoolFilter()
    {
        $filter = (new GeneralFilterContainer())
            ->addFilter('test1', new BoolFilter())
            ->addFilter('test2', new BoolFilter());

        if (!($filter instanceof GeneralFilterContainer)) {
            $this->fail('Type error');
        }

        $before = [
            'test1' => 1,
            'test2' => 0
        ];
        $after = $filter($before);

        $this->assertSame(
            [
                'test1' => true,
                'test2' => false
            ],
            $after
        );

        try {
            $typeError = [
                'test1' => true,
                'test2' => []
            ];
            $filter($typeError);
            $this->fail('Expected a TypeError');
        } catch (\TypeError $ex) {
        }
    }

    /**
     * @covers BoolArrayFilter
     */
    public function testBoolArrayFilter()
    {
        $filter = (new GeneralFilterContainer())
            ->addFilter('test', new BoolArrayFilter());

        if (!($filter instanceof GeneralFilterContainer)) {
            $this->fail('Type error');
        }

        $before = [
            'test' => [
                '',
                null,
                0,
                1,
                'true',
                'false'
            ]
        ];
        $after = $filter($before);

        $this->assertSame(
            [
                'test' => [
                    false,
                    false,
                    false,
                    true,
                    true,
                    true
                ]
            ],
            $after
        );

        try {
            $typeError = [[]];
            $filter($typeError);
            $this->fail('Expected a TypeError');
        } catch (\TypeError $ex) {
        }
    }
    /**
     * @covers FloatFilter
     */
    public function testFloatFilter()
    {
        $filter = (new GeneralFilterContainer())
            ->addFilter('test1', new FloatFilter())
            ->addFilter('test2', new FloatFilter())
            ->addFilter('test3', new FloatFilter());

        if (!($filter instanceof GeneralFilterContainer)) {
            $this->fail('Type error');
        }

        $before = [
            'test1' => '22.7',
            'test2' => null,
            'test3' => M_E
        ];
        $after = $filter($before);

        $this->assertSame(
            [
                'test1' => 22.7,
                'test2' => 0.0,
                'test3' => M_E
            ],
            $after
        );

        try {
            $typeError = [
                'test1' => '22',
                'test2' => 0,
                'test3' => []
            ];
            $filter($typeError);
            $this->fail('Expected a TypeError');
        } catch (\TypeError $ex) {
        }
    }


    /**
     * @covers FloatArrayFilter
     */
    public function testFloatArrayFilter()
    {
        $filter = (new GeneralFilterContainer())
            ->addFilter('test', new FloatArrayFilter());

        if (!($filter instanceof GeneralFilterContainer)) {
            $this->fail('Type error');
        }

        $before = [
            'test' => [
                null,
                '',
                0,
                33
            ]
        ];
        $after = $filter($before);

        $this->assertSame(
            [
                'test' => [
                    0.0,
                    0.0,
                    0.0,
                    33.0
                ]
            ],
            $after
        );

        try {
            $typeError = [[]];
            $filter($typeError);
            $this->fail('Expected a TypeError');
        } catch (\TypeError $ex) {
        }
    }

    /**
     * @covers IntFilter
     */
    public function testIntFilter()
    {
        $filter = (new GeneralFilterContainer())
            ->addFilter('test1', new IntFilter())
            ->addFilter('test2', new IntFilter())
            ->addFilter('test3', new IntFilter());

        if (!($filter instanceof GeneralFilterContainer)) {
            $this->fail('Type error');
        }

        $before = [
            'test1' => '22',
            'test2' => null,
            'test3' => PHP_INT_MAX
        ];
        $after = $filter($before);

        $this->assertSame(
            [
                'test1' => 22,
                'test2' => 0,
                'test3' => PHP_INT_MAX
            ],
            $after
        );

        try {
            $typeError = [
                'test1' => '22',
                'test2' => 0,
                'test3' => []
            ];
            $filter($typeError);
            $this->fail('Expected a TypeError');
        } catch (\TypeError $ex) {
        }


        try {
            $typeError = [
                'test1' => '22',
                'test2' => 0,
                'test3' => '1.5'
            ];
            $filter($typeError);
            $this->fail('Expected a TypeError');
        } catch (\TypeError $ex) {
        }
    }


    /**
     * @covers IntArrayFilter
     */
    public function testIntArrayFilter()
    {
        $filter = (new GeneralFilterContainer())
            ->addFilter('test', new IntArrayFilter());

        if (!($filter instanceof GeneralFilterContainer)) {
            $this->fail('Type error');
        }

        $before = [
            'test' => [
                null,
                '',
                0,
                33
            ]
        ];
        $after = $filter($before);

        $this->assertSame(
            [
                'test' => [
                    0,
                    0,
                    0,
                    33
                ]
            ],
            $after
        );

        try {
            $typeError = [[]];
            $filter($typeError);
            $this->fail('Expected a TypeError');
        } catch (\TypeError $ex) {
        }
    }


    /**
     * @covers StringFilter
     */
    public function testStringFilter()
    {
        $filter = (new GeneralFilterContainer())
            ->addFilter('test1', new StringFilter())
            ->addFilter('test2', new StringFilter())
            ->addFilter('test3', new StringFilter());

        if (!($filter instanceof GeneralFilterContainer)) {
            $this->fail('Type error');
        }

        $before = [
            'test1' => 22.7,
            'test2' => null,
            'test3' => 'abcdefg'
        ];
        $after = $filter($before);

        $this->assertSame(
            [
                'test1' => '22.7',
                'test2' => '',
                'test3' => 'abcdefg'
            ],
            $after
        );

        try {
            $typeError = [
                'test1' => '22',
                'test2' => 0,
                'test3' => []
            ];
            $filter($typeError);
            $this->fail('Expected a TypeError');
        } catch (\TypeError $ex) {
        }
    }


    /**
     * @covers StringArrayFilter
     */
    public function testStringArrayFilter()
    {
        $filter = (new GeneralFilterContainer())
            ->addFilter('test', new StringArrayFilter());

        if (!($filter instanceof GeneralFilterContainer)) {
            $this->fail('Type error');
        }

        $before = [
            'test' => [
                null,
                '',
                0,
                33
            ]
        ];
        $after = $filter($before);

        $this->assertSame(
            [
                'test' => [
                    '',
                    '',
                    '0',
                    '33'
                ]
            ],
            $after
        );

        try {
            $typeError = [[]];
            $filter($typeError);
            $this->fail('Expected a TypeError');
        } catch (\TypeError $ex) {
        }
    }
}
