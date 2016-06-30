<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\Blog;

use \Airship\Engine\Security\Filter\{
    ArrayFilter,
    IntFilter,
    InputFilterContainer,
    StringFilter
};

/**
 * Class EditSeriesFilter
 * @package Airship\Cabin\Bridge\Filter\Account
 */
class EditSeriesFilter extends InputFilterContainer
{
    /**
     * EditSeriesFilter constructor.
     */
    public function __construct()
    {
        $this
            ->addFilter(
                'name',
                (new StringFilter())
                    ->addCallback([StringFilter::class, 'nonEmpty'])
            )
            ->addFilter('author', new IntFilter())
            ->addFilter('preamble', new StringFilter())
            ->addFilter('format', new StringFilter())
            ->addFilter('config', new ArrayFilter())
            ->addFilter('items', new StringFilter());
    }
}
