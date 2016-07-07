<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\Blog;

use Airship\Engine\Security\Filter\{
    InputFilterContainer,
    StringFilter
};

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
