<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\Blog;

use Airship\Engine\Security\Filter\{
    BoolFilter,
    IntFilter,
    InputFilterContainer,
    StringArrayFilter,
    StringFilter
};

/**
 * Class EditPostFilter
 * @package Airship\Cabin\Bridge\Filter\Account
 */
class EditPostFilter extends InputFilterContainer
{
    /**
     * EditPostFilter constructor.
     */
    public function __construct()
    {
        $this
            ->addFilter('author', new IntFilter())
            ->addFilter('blog_post_body', new StringFilter())
            ->addFilter('category', new IntFilter())
            ->addFilter('description', new StringFilter())
            ->addFilter(
                'format',
                (new StringFilter())
                    ->setDefault('Rich Text')
            )
            ->addFilter('metadata', new StringArrayFilter())
            ->addFilter(
                'title',
                (new StringFilter())
                    ->addCallback([StringFilter::class, 'nonEmpty'])
            )
            ->addFilter('redirect_slug', new BoolFilter())
            ->addFilter('save_btn', new StringFilter())
            ->addFilter('slug', new StringFilter());
    }
}
