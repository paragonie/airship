<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\Account;

use ParagonIE\Ionizer\Filter\StringFilter;
use ParagonIE\Ionizer\InputFilterContainer;

/**
 * Class BoardFilter
 * @package Airship\Cabin\Bridge\Filter\Account
 */
class BoardFilter extends InputFilterContainer
{
    /**
     * BoardFilter constructor.
     */
    public function __construct()
    {
        $this->addFilter(
                'username',
                (new StringFilter())
                    ->addCallback([StringFilter::class, 'nonEmpty'])
            )
            ->addFilter(
                'passphrase',
                (new StringFilter())
                    ->addCallback([StringFilter::class, 'nonEmpty'])
            );
    }
}
