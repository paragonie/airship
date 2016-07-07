<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\Blog;

use Airship\Engine\Security\Filter\{
    InputFilterContainer,
    StringFilter
};

/**
 * Class EditTagFilter
 * @package Airship\Cabin\Bridge\Filter\Account
 */
class EditTagFilter extends InputFilterContainer
{
    /**
     * EditTagFilter constructor.
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
