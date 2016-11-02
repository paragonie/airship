<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\Crew;

use Airship\Engine\Security\Filter\{
    InputFilterContainer,
    StringFilter
};

/**
 * Class NewUserFilter
 * @package Airship\Cabin\Bridge\Filter\Account
 */
class NewUserFilter extends InputFilterContainer
{
    /**
     * NewUserFilter constructor.
     */
    public function __construct()
    {
        $this
            ->addFilter('username', new StringFilter())
            ->addFilter('password', new StringFilter())
            ->addFilter('email', new StringFilter())
            ->addFilter('uniqueid', new StringFilter())
            ->addFilter('display_name', new StringFilter())
            ->addFilter('real_name', new StringFilter());
    }
}
