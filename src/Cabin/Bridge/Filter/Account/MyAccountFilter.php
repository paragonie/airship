<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\Account;

use ParagonIE\Ionizer\Filter\{
    BoolFilter,
    StringFilter
};
use ParagonIE\Ionizer\InputFilterContainer;

/**
 * Class MyAccountFilter
 * @package Airship\Cabin\Bridge\Filter\Account
 */
class MyAccountFilter extends InputFilterContainer
{
    /**
     * MyAccountFilter constructor.
     */
    public function __construct()
    {
        $this
            ->addFilter('allow_reset', new BoolFilter())
            ->addFilter(
                'display_name',
                (new StringFilter())->addCallback('trim')
            )
            ->addFilter(
                'email',
                (new StringFilter())->addCallback('trim')
            )
            ->addFilter(
                'gpg_public_key',
                (new StringFilter())->addCallback('trim')
            )
            ->addFilter('passphrase', new StringFilter())
            ->addFilter('publicprofile', new BoolFilter())
            ->addFilter(
                'real_name',
                (new StringFilter())->addCallback('trim')
            );
    }
}
