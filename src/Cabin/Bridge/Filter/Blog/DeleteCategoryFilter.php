<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\Blog;

use ParagonIE\Ionizer\Filter\{
    BoolFilter,
    IntFilter
};
use ParagonIE\Ionizer\InputFilterContainer;

/**
 * Class DeleteCategoryFilter
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
