<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\Account;

use ParagonIE\Ionizer\Filter\StringFilter;
use ParagonIE\Ionizer\GeneralFilterContainer;

/**
 * Class PreferencesFilter
 * @package Airship\Cabin\Bridge\Filter\Account
 */
class PreferencesFilter extends GeneralFilterContainer
{
    /**
     * Build the filter from configuration
     *
     * @param array $cabinNamespaces
     * @param array $motifs
     * @return PreferencesFilter
     */
    public static function fromConfig(
        array $cabinNamespaces = [],
        array $motifs = []
    ): self {
        $filterContainer = new PreferencesFilter();
        foreach ($cabinNamespaces as $cabin) {
            $activeCabin = $motifs[$cabin];
            $filterContainer->addFilter(
                'prefs.motif.' . $cabin,
                (new StringFilter())->addCallback(
                    function (string $selected) use ($cabin, $activeCabin): string {
                        foreach ($activeCabin as $cabinConfig) {
                            if ($selected === $cabinConfig['path']) {
                                return $selected;
                            }
                        }
                        return '';
                    }
                )
            );
        }
        return $filterContainer;
    }
}