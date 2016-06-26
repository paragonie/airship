<?php
declare(strict_types=1);
namespace Airship\Engine\Security\Filter;

/**
 * Class IntFilter
 * @package Airship\Engine\Security\Filter
 */
class IntFilter extends InputFilter
{
    /**
     * @var mixed
     */
    protected $default = 0;

    /**
     * @var string
     */
    protected $type = 'int';
}
