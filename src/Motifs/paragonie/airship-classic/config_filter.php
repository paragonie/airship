<?php
declare(strict_types=1);

use Airship\Engine\Security\Filter\{
    BoolFilter,
    GeneralFilterContainer
};

/**
 * @return $motifInputFilter
 */
$motifInputFilter = (new GeneralFilterContainer())
    ->addFilter(
        'bridge.gradient',
        new BoolFilter()
    )
;
