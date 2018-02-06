<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\Author;

use ParagonIE\Ionizer\Filter\{
    BoolFilter,
    StringFilter
};
use ParagonIE\Ionizer\InputFilterContainer;

/**
 * Class UsersFilter
 * @package Airship\Cabin\Bridge\Filter\Account
 */
class UsersFilter extends InputFilterContainer
{
    /**
     * UsersFilter constructor.
     */
    public function __construct()
    {
        $this
            ->addFilter('add_user', new StringFilter())
            ->addFilter('in_charge', new BoolFilter())
            ->addFilter('remove_user', new StringFilter())
            ->addFilter('toggle_owner', new StringFilter());
    }
}
