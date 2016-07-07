<?php
declare(strict_types=1);
namespace Airship\Cabin\Hull\Filter\BlogPosts;

use Airship\Engine\Security\Filter\{
    InputFilterContainer,
    IntFilter,
    StringFilter
};

/**
 * Class CommentFilter
 * @package Airship\Cabin\Hull\Filter\BlogPosts
 */
class CommentFilter extends InputFilterContainer
{
    /**
     * CommentDirFilter constructor.
     */
    public function __construct()
    {
        $this->addFilter('name', new StringFilter())
            ->addFilter('email', new StringFilter())
            ->addFilter('url', new StringFilter())
            ->addFilter('author', new IntFilter())
            ->addFilter('message', new StringFilter());
    }
}
