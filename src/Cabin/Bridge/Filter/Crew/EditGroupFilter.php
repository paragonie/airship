<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\Crew;

use ParagonIE\Ionizer\Filter\{
    BoolFilter,
    IntFilter,
    StringFilter
};
use ParagonIE\Ionizer\InputFilterContainer;

/**
 * Class EditGroupFilter
 * @package Airship\Cabin\Bridge\Filter\Account
 */
class EditGroupFilter extends InputFilterContainer
{
    /**
     * EditGroupFilter constructor.
     */
    public function __construct()
    {
        $this
            ->addFilter(
                'name',
                (new StringFilter())
                    ->addCallback([StringFilter::class, 'nonEmpty'])
            )
            ->addFilter('parent', new IntFilter())
            ->addFilter('superuser', new BoolFilter());
    }
}
