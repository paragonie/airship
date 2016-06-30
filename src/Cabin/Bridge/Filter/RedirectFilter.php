<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter;

use \Airship\Engine\Security\Filter\{
    InputFilterContainer,
    StringFilter
};

/**
 * Class RedirectFilter
 * @package Airship\Cabin\Bridge\Filter\Account
 */
class RedirectFilter extends InputFilterContainer
{
    /**
     * RecoveryFilter constructor.
     */
    public function __construct()
    {
        $urlFilter = (new StringFilter())
            ->addCallback([StringFilter::class, 'nonEmpty']);
        $this
            ->addFilter('new_url', $urlFilter)
            ->addFilter('old_url', $urlFilter);
    }
}
