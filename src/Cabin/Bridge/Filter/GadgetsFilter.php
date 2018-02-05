<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter;

use ParagonIE\Ionizer\Filter\{
    BoolFilter,
    IntFilter
};
use ParagonIE\Ionizer\GeneralFilterContainer;

/**
 * Class GadgetsFilter
 * @package Airship\Cabin\Bridge\Filter\Account
 */
class GadgetsFilter extends GeneralFilterContainer
{
    /**
     * Build the filter from configuration
     *
     * @param array $gadgets
     * @return GadgetsFilter
     */
    public static function fromConfig(
        array $gadgets = []
    ): self {
        $filterContainer = new GadgetsFilter();
        foreach ($gadgets as $k) {
            $filterContainer
                ->addFilter('gadget_enabled.' . $k, new BoolFilter())
                ->addFilter('gadget_order.' . $k, new IntFilter());
        }
        return $filterContainer;
    }
}