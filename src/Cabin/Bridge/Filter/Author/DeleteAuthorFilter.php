<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\Author;

use Airship\Engine\Security\Filter\{
    BoolFilter,
    InputFilterContainer,
    IntFilter
};

/**
 * Class AuthorFilter
 * @package Airship\Cabin\Bridge\Filter\Account
 */
class DeleteAuthorFilter extends InputFilterContainer
{
    /**
     * AuthorFilter constructor.
     */
    public function __construct()
    {
        $this
            ->addFilter('confirm', new BoolFilter())
            ->addFilter('reassign', new IntFilter())
        ;
    }
}
