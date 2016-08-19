<?php
declare(strict_types=1);
namespace Airship\Cabin\Hull;

use Airship\Engine\Security\Filter\{
    BoolFilter,
    InputFilterContainer,
    IntFilter,
    StringFilter
};

/**
 * Class ConfigFilter
 * @package Airship\Cabin\Hull
 */
class ConfigFilter extends InputFilterContainer
{
    /**
     * ConfigFilter constructor.
     *
     * Specifies the filter rules for the cabin configuration POST rules.
     */
    public function __construct()
    {
        $this

        /* config_extra */
            ->addFilter('config_extra.blog.cachelists', new BoolFilter())
            ->addFilter('config_extra.blog.comments.depth_max', new IntFilter())
            ->addFilter('config_extra.blog.comments.enabled', new BoolFilter())
            ->addFilter('config_extra.blog.comments.guests', new BoolFilter())
            ->addFilter('config_extra.blog.comments.recaptcha', new BoolFilter())
            ->addFilter('config_extra.blog.per_page', new IntFilter())

            ->addFilter('config_extra.file.cache', new IntFilter())

            ->addFilter('config_extra.homepage.blog-posts', (new IntFilter())->setDefault(5))
            ->addFilter('config_extra.cache-secret', (new StringFilter())->setDefault(\Airship\uniqueId(33)))

            ->addFilter('config_extra.recaptcha.secret-key', new StringFilter())
            ->addFilter('config_extra.recaptcha.site-key', new StringFilter())

        /* twig_vars */
            ->addFilter('twig_vars.active-motif', new StringFilter())
            ->addFilter('twig_vars.title', new StringFilter())
            ->addFilter('twig_vars.tagline', new StringFilter())
            ->addFilter('twig_vars.blog.title', new StringFilter())
            ->addFilter('twig_vars.blog.tagline', new StringFilter())
        ;
    }
}
