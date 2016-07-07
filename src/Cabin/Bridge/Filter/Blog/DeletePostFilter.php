<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\Blog;

use Airship\Engine\Security\Filter\{
    BoolFilter,
    InputFilterContainer,
    StringFilter
};

/**
 * Class DeletePostFilter
 * @package Airship\Cabin\Bridge\Filter\Account
 */
class DeletePostFilter extends InputFilterContainer
{
    /**
     * DeletePostFilter constructor.
     */
    public function __construct()
    {
        $this
            ->addFilter('create_redirect', new BoolFilter())
            ->addFilter(
                'redirect_url',
                (new StringFilter())
                    ->setDefault('/')
            );
    }
}
