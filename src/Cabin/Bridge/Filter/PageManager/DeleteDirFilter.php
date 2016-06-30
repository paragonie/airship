<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\PageManager;

use \Airship\Engine\Security\Filter\{
    BoolFilter,
    IntFilter,
    InputFilterContainer
};

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
