<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\Blog;

use \Airship\Engine\Security\Filter\{
    InputFilterContainer,
    StringFilter
};

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
