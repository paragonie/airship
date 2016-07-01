<?php
declare(strict_types=1);
namespace Airship\Engine\Security\Filter;

use \Airship\Alerts\Security\Filter\UnsupportedOperation;
use \Airship\Engine\Contract\Security\FilterInterface;


/**
 * Class BoolFilter
 * @package Airship\Engine\Security\Filter
 */
class BoolFilter extends InputFilter
{
    /**
     * @var mixed
     */
    protected $default = false;

    /**
     * @var string
     */
    protected $type = 'bool';

    /**
     * Sets the expected input type (e.g. string, boolean)
     *
     * @param string $typeIndicator
     * @return FilterInterface
     * @throws UnsupportedOperation
     */
    public function setType(string $typeIndicator): FilterInterface
    {
        if ($typeIndicator !== 'bool') {
            throw new UnsupportedOperation(
                'Type must always be set to "bool".'
            );
        }
        return parent::setType('bool');
    }

    /**
     * Set the default value (not applicable to booleans)
     *
     * @param mixed $value
     * @return FilterInterface
     * @throws UnsupportedOperation
     */
    public function setDefault($value): FilterInterface
    {
        if ($value !== false) {
            throw new UnsupportedOperation(
                'Default must always be set to FALSE.'
            );
        }
        return parent::setDefault(false);
    }
}