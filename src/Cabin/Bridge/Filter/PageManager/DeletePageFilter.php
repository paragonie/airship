<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\PageManager;

use ParagonIE\Ionizer\InputFilterContainer;
use ParagonIE\Ionizer\Filter\{
    BoolFilter,
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
