<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\Account;

use Airship\Engine\Security\Filter\{
    GeneralFilterContainer,
    StringFilter
};

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
                    function ($selected) use ($cabin, $activeCabin): string {
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