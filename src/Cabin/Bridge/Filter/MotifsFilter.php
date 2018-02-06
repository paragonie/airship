<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter;

use ParagonIE\Ionizer\GeneralFilterContainer;
use ParagonIE\Ionizer\Filter\BoolFilter;

/**
 * Class MotifsFilter
 * @package Airship\Cabin\Bridge\Filter\Account
 */
class MotifsFilter extends GeneralFilterContainer
{
    /**
     * Build the filter from configuration
     *
     * @param array $motifs
     * @return MotifsFilter
     */
    public static function fromConfig(
        array $motifs = []
    ): self {
        $filterContainer = new MotifsFilter();
        foreach ($motifs as $i) {
            $filterContainer
                ->addFilter('motifs.' . $i . '.enabled', new BoolFilter());
        }
        return $filterContainer;
    }
}