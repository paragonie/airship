<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\PageManager;

use ParagonIE\Ionizer\Filter\{
    BoolFilter,
    IntFilter
};
use ParagonIE\Ionizer\InputFilterContainer;

/**
 * Class DeleteDirFilter
 * @package Airship\Cabin\Bridge\Filter\Account
 */
class DeleteDirFilter extends InputFilterContainer
{
    /**
     * DeleteDirFilter constructor.
     */
    public function __construct()
    {
        $this->addFilter('create_redirect', new BoolFilter())
            ->addFilter('move_destination', new IntFilter());
    }
}
