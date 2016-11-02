<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\Blog;

use Airship\Engine\Security\Filter\{
    BoolFilter,
    IntFilter,
    InputFilterContainer,
    StringFilter
};

/**
 * Class EditCategoryFilter
 * @package Airship\Cabin\Bridge\Filter\Account
 */
class EditCategoryFilter extends InputFilterContainer
{
    /**
     * EditCategoryFilter constructor.
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
            ->addFilter('preamble', new StringFilter())
            ->addFilter('redirect_slug', new BoolFilter())
            ->addFilter('slug', new StringFilter())
        ;
    }
}
