<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\Account;

use ParagonIE\Ionizer\Filter\StringFilter;
use ParagonIE\Ionizer\InputFilterContainer;

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
