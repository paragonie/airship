<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\PageManager;

use \Airship\Engine\Security\Filter\{
    BoolFilter,
    InputFilterContainer,
    StringFilter
};

/**
 * Class RenameFilter
 * @package Airship\Cabin\Bridge\Filter\Account
 */
class RenameFilter extends InputFilterContainer
{
    /**
     * RenameFilter constructor.
     */
    public function __construct()
    {
        $this->addFilter('create_redirect', new BoolFilter())
        ->addFilter(
            'url',
            (new StringFilter())
                ->addCallback([StringFilter::class, 'nonEmpty'])
        );
    }
}
