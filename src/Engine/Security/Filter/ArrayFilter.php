<?php
declare(strict_types=1);
namespace Airship\Engine\Security\Filter;

/**
 * Class ArrayFilter
 * @package Airship\Engine\Security\Filter
 */
class ArrayFilter extends InputFilter
{

    /**
     * @var mixed
     */
    protected $default = [];

    /**
     * @var string
     */
    protected $type = 'array';
}
