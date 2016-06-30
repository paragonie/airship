<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\Admin;

use \Airship\Engine\Security\Filter\{
    BoolFilter,
    InputFilterContainer,
    IntFilter,
    StringFilter
};

/**
 * Class NotaryFilter
 * @package Airship\Cabin\Bridge\Filter\Account
 */
class NotaryFilter extends InputFilterContainer
{
    /**
     * NotaryFilter constructor.
     */
    public function __construct()
    {
        $this->addFilter('delete_notary', new StringFilter())
            ->addFilter('new_notary', new StringFilter());
    }
}
