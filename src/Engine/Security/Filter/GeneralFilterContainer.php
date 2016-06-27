<?php
declare(strict_types=1);
namespace Airship\Engine\Security\Filter;

/**
 * Class GeneralFilterContainer
 *
 * Want to build an input container at runtime? Start with one of these.
 *
 * @package Airship\Engine\Security\Filter
 */
class GeneralFilterContainer extends InputFilterContainer
{
    /**
     * GeneralFilterContainer constructor.
     */
    public function __construct()
    {
        // NOP. This just can't be abstract.
    }
}
