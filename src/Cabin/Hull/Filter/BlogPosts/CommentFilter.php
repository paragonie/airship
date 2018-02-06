<?php
declare(strict_types=1);
namespace Airship\Cabin\Hull\Filter\BlogPosts;

use ParagonIE\Ionizer\Filter\{
    IntFilter,
    StringFilter
};
use ParagonIE\Ionizer\InputFilterContainer;

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
