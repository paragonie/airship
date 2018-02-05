<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\Crew;

use ParagonIE\Ionizer\Filter\{
    BoolFilter,
    IntFilter
};
use ParagonIE\Ionizer\InputFilterContainer;

/**
 * Class DeleteGroupFilter
 * @package Airship\Cabin\Bridge\Filter\Account
 */
class DeleteGroupFilter extends InputFilterContainer
{
    /**
     * DeleteGroupFilter constructor.
     */
    public function __construct()
    {
        $this
            ->addFilter('confirm', new BoolFilter())
            ->addFilter('move_children', new IntFilter())
        ;
    }
}
