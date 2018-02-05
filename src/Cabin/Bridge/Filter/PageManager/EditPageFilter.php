<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\PageManager;

use ParagonIE\Ionizer\InputFilterContainer;
use ParagonIE\Ionizer\Filter\{
    BoolFilter,
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
