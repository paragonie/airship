<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\Blog;

use ParagonIE\Ionizer\Filter\StringFilter;
use ParagonIE\Ionizer\InputFilterContainer;

/**
 * Class CommentFilter
 * @package Airship\Cabin\Bridge\Filter\Account
 */
class CommentFilter extends InputFilterContainer
{
    /**
     * CommentFilter constructor.
     */
    public function __construct()
    {
        $this->addFilter('comment_btn', new StringFilter());
    }
}
