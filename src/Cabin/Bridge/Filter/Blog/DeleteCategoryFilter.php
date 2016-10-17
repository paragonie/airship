<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\Blog;

use Airship\Engine\Security\Filter\{
    BoolFilter,
    InputFilterContainer,
    IntFilter
};

/**
 * Class DeletePostFilter
 * @package Airship\Cabin\Bridge\Filter\Account
 */
class DeleteCategoryFilter extends InputFilterContainer
{
    /**
     * DeletePostFilter constructor.
     */
    public function __construct()
    {
        $this
            ->addFilter('confirm', new BoolFilter())
            ->addFilter('moveChildrenTo', new IntFilter())
        ;
    }
}
