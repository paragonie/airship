<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\Permissions;

use ParagonIE\Ionizer\InputFilterContainer;
use ParagonIE\Ionizer\Filter\StringFilter;

/**
 * Class CabinSubmenuFilter
 * @package Airship\Cabin\Bridge\Filter\Account
 */
class CabinSubmenuFilter extends InputFilterContainer
{
    /**
     * CabinSubmenuFilter constructor.
     */
    public function __construct()
    {
        $this->addFilter('new_action', new StringFilter())
            ->addFilter('new_context', new StringFilter());
    }
}
