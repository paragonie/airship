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
        'bridge.gradient',
        new BoolFilter()
    )
    ->addFilter(
        'style-csp-nonce',
        new BoolFilter()
    )
    ->addFilter(
        'hull.blog-header.enabled',
        new BoolFilter()
    )
    ->addFilter(
        'hull.blog-header.background-image',
        new StringFilter()
    )
    ->addFilter(
        'hull.blog-header.color',
        (new StringFilter())->addCallback(
            function ($input) {
                if (!\preg_match('/^0-9A-Fa-f{3,6}$/', $input)) {
                    return '';
                }
                return $input;
            }
        )
    )
;
