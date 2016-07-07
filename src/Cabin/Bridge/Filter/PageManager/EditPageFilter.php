<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\PageManager;

use Airship\Engine\Security\Filter\{
    BoolFilter,
    InputFilterContainer,
    StringFilter
};

/**
 * Class EditPageFilter
 * @package Airship\Cabin\Bridge\Filter\Account
 */
class EditPageFilter extends InputFilterContainer
{
    /**
     * EditPageFilter constructor.
     */
    public function __construct()
    {
        $this->addFilter('publish', new BoolFilter())
            ->addFilter(
                'format',
                (new StringFilter())
                    ->setDefault('Rich Text')
            )
            ->addFilter('body', new StringFilter());
    }
}
