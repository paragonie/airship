<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\Account;

use \Airship\Engine\Security\Filter\{
    InputFilterContainer,
    BoolFilter
};

/**
 * Class TwoFactorFilter
 * @package Airship\Cabin\Bridge\Filter\Account
 */
class TwoFactorFilter extends InputFilterContainer
{
    /**
     * TwoFactorFilter constructor.
     */
    public function __construct()
    {
        $this->addFilter('enable_2factor', new BoolFilter())
            ->addFilter('reset_secret', new BoolFilter());
    }
}
