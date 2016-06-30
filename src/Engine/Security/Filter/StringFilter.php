<?php
declare(strict_types=1);
namespace Airship\Engine\Security\Filter;

use \Airship\Engine\Security\Util;

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

    /**
     * @param string $input
     * @return string
     * @throws \TypeError
     */
    public static function nonEmpty(string $input): string
    {
        if (Util::stringLength($input) < 1) {
            throw new \TypeError();
        }
        return $input;
    }
}
