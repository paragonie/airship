<?php
declare(strict_types=1);
namespace Airship\Engine\Security\Filter;

/**
 * Class StringFilter
 * @package Airship\Engine\Security\Filter
 */
class StringFilter extends InputFilter
{

    /**
     * @var mixed
     */
    protected $default = '';

    /**
     * @var string
     */
    protected $type = 'string';
}
