<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\Crew;

use ParagonIE\Ionizer\Filter\BoolFilter;
use ParagonIE\Ionizer\InputFilterContainer;

/**
 * Class DeleteUserFilter
 * @package Airship\Cabin\Bridge\Filter\Account
 */
class DeleteUserFilter extends InputFilterContainer
{
    /**
     * DeleteUserFilter constructor.
     */
    public function __construct()
    {
        $this
            ->addFilter('confirm', new BoolFilter());
    }
}
