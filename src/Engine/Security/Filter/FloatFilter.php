<?php
declare(strict_types=1);
namespace Airship\Engine\Security\Filter;

/**
 * Class FloatFilter
 * @package Airship\Engine\Security\Filter
 */
class FloatFilter extends InputFilter
{
    /**
     * @var mixed
     */
    protected $default = 0;

    /**
     * @var string
     */
    protected $type = 'float';
}
