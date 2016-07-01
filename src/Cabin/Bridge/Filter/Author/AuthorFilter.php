<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\Author;

use \Airship\Engine\Security\Filter\{
    InputFilterContainer,
    StringFilter
};

/**
 * Class AuthorFilter
 * @package Airship\Cabin\Bridge\Filter\Account
 */
class AuthorFilter extends InputFilterContainer
{
    /**
     * AuthorFilter constructor.
     */
    public function __construct()
    {
        $this
            ->addFilter(
                'name',
                (new StringFilter())
                    ->addCallback([StringFilter::class, 'nonEmpty'])
            )
            ->addFilter('byline', new StringFilter())
            ->addFilter('format', (new StringFilter())->setDefault('Rich Text'))
            ->addFilter('biography', new StringFilter());
    }
}
