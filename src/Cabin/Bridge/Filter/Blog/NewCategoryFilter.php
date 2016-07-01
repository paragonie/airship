<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\Blog;

use \Airship\Engine\Security\Filter\{
    IntFilter,
    InputFilterContainer,
    StringFilter
};

/**
 * Class NewCategoryFilter
 * @package Airship\Cabin\Bridge\Filter\Account
 */
class NewCategoryFilter extends InputFilterContainer
{
    /**
     * NewCategoryFilter constructor.
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
            ->addFilter('preamble', new StringFilter());
    }
}
