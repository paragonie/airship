<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\Permissions;

use Airship\Engine\Security\Filter\{
    InputFilterContainer,
    StringFilter
};

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
