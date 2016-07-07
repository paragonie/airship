<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\Crew;

use Airship\Engine\Security\Filter\{
    BoolFilter,
    InputFilterContainer,
    IntFilter,
    StringFilter
};

/**
 * Class NewGroupFilter
 * @package Airship\Cabin\Bridge\Filter\Account
 */
class NewGroupFilter extends InputFilterContainer
{
    /**
     * NewGroupFilter constructor.
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
