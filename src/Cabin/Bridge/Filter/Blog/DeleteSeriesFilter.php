<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\Blog;

use Airship\Engine\Security\Filter\{
    BoolFilter,
    InputFilterContainer
};

/**
 * Class DeleteSeriesFilter
 * @package Airship\Cabin\Bridge\Filter\Account
 */
class DeleteSeriesFilter extends InputFilterContainer
{
    /**
     * DeletePostFilter constructor.
     */
    public function __construct()
    {
        $this
            ->addFilter('delete_btn', new BoolFilter())
        ;
    }
}
