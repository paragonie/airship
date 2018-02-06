<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\PageManager;

use ParagonIE\Ionizer\Filter\{
    BoolFilter,
    StringFilter
};
use ParagonIE\Ionizer\InputFilterContainer;

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
