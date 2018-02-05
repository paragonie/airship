<?php
declare(strict_types=1);

use ParagonIE\Ionizer\Filter\{
    BoolFilter,
    FloatFilter,
    IntFilter,
    StringFilter
};
use ParagonIE\Ionizer\GeneralFilterContainer;

$colorCallback = function ($input): int {
    if ($input < 0 || $input > 255) {
        throw new \TypeError();
    }
    return (int) $input;
};
$alphaCallback = function ($input): float {
    if ($input < 0.0 || $input > 1.0) {
        throw new \TypeError();
    }
    return (float) $input;
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
        'motif_config.hull.background.enabled',
        new BoolFilter()
    )
    ->addFilter(
        'motif_config.hull.background.tile',
        new BoolFilter()
    )
    ->addFilter(
        'motif_config.hull.background.image',
        new StringFilter()
    )
    ->addFilter(
        'motif_config.hull.background.shade.red',
        (new IntFilter())
            ->addCallback($colorCallback)
    )
    ->addFilter(
        'motif_config.hull.background.shade.green',
        (new IntFilter())
            ->addCallback($colorCallback)
    )
    ->addFilter(
        'motif_config.hull.background.shade.blue',
        (new IntFilter())
            ->addCallback($colorCallback)
    )
    ->addFilter(
        'motif_config.hull.background.shade.alpha',
        (new FloatFilter())
            ->addCallback($alphaCallback)
    )
    ->addFilter(
        'motif_config.hull.footer.override',
        new BoolFilter()
    )
    ->addFilter(
        'motif_config.hull.footer.html',
        new StringFilter()
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
        'motif_config.hull.blog-header.font.red',
        (new IntFilter())
            ->addCallback($colorCallback)
    )
    ->addFilter(
        'motif_config.hull.blog-header.font.green',
        (new IntFilter())
            ->addCallback($colorCallback)
    )
    ->addFilter(
        'motif_config.hull.blog-header.font.blue',
        (new IntFilter())
            ->addCallback($colorCallback)
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
