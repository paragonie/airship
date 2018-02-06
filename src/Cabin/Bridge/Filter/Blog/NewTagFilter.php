<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\Blog;

use ParagonIE\Ionizer\Filter\StringFilter;
use ParagonIE\Ionizer\InputFilterContainer;

/**
 * Class NewTagFilter
 * @package Airship\Cabin\Bridge\Filter\Account
 */
class NewTagFilter extends InputFilterContainer
{
    /**
     * NewTagFilter constructor.
     */
    public function __construct()
    {
        $this->addFilter(
            'name',
            (new StringFilter())
                ->addCallback([StringFilter::class, 'nonEmpty'])
        );
    }
}
