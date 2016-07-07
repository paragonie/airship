<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\Account;

use Airship\Engine\Security\Filter\{
    InputFilterContainer,
    StringFilter
};

/**
 * Class RecoveryFilter
 * @package Airship\Cabin\Bridge\Filter\Account
 */
class RecoveryFilter extends InputFilterContainer
{
    /**
     * RecoveryFilter constructor.
     */
    public function __construct()
    {
        $this->addFilter(
            'forgot_passphrase_for',
            (new StringFilter())
                ->addCallback([StringFilter::class, 'nonEmpty'])
        );
    }
}
