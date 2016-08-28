<?php
declare(strict_types=1);

use Airship\Engine\Security\Filter\{
    BoolFilter,
    GeneralFilterContainer,
    StringFilter
};

/**
 * @return $motifInputFilter
 */
$motifInputFilter = (new GeneralFilterContainer())
    ->addFilter(
        'motif_config.bridge.gradient',
        new BoolFilter()
    )
    ->addFilter(
        'motif_config.hull.blog-header.enabled',
        new BoolFilter()
    )
    ->addFilter(
        'motif_config.hull.blog-header.background-image',
        new StringFilter()
    )
    ->addFilter(
        'motif_config.hull.blog-header.color',
        (new StringFilter())
            ->addCallback(
                function ($input) {
                    if (!\preg_match('/^[0-9A-Fa-f]{3,6}$/', $input)) {
                        return null;
                    }
                    return $input;
                }
            )
            ->setDefault('181818')
    )
;
