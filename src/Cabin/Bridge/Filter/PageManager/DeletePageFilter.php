<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\PageManager;

use Airship\Engine\Security\Filter\{
    BoolFilter,
    InputFilterContainer,
    StringFilter
};

/**
 * Class DeletePageFilter
 * @package Airship\Cabin\Bridge\Filter\Account
 */
class DeletePageFilter extends InputFilterContainer
{
    /**
     * DeletePageFilter constructor.
     */
    public function __construct()
    {
        $this->addFilter('create_redirect', new BoolFilter())
            ->addFilter('redirect_to', new StringFilter());
    }
}
