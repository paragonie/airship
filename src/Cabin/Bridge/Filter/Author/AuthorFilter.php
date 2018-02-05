<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\Author;

use ParagonIE\Ionizer\Filter\{
    BoolFilter,
    StringFilter
};
use ParagonIE\Ionizer\InputFilterContainer;

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
            ->addFilter('format',
                (new StringFilter())->setDefault('Rich Text')
            )
            ->addFilter('biography', new StringFilter())
            ->addFilter('redirect_slug', new BoolFilter())
            ->addFilter('slug', new StringFilter())
        ;
    }
}
