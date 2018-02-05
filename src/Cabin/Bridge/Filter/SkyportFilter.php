<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter;

use ParagonIE\Ionizer\Filter\StringFilter;
use ParagonIE\Ionizer\InputFilterContainer;

/**
 * Class SkyportFilter
 * @package Airship\Cabin\Bridge\Filter\Account
 */
class SkyportFilter extends InputFilterContainer
{
    /**
     * RecoveryFilter constructor.
     */
    public function __construct()
    {
        $this->addFilter(
                'type',
                (new StringFilter())->addCallback(
                    function (string $input): string
                    {
                        switch ($input) {
                            case 'Cabin':
                            case 'Gadget':
                            case 'Motif':
                                return $input;
                            default:
                                throw new \TypeError();
                        }
                    }
                )
            )
            ->addFilter(
                'package',
                (new StringFilter())
                    ->addCallback([StringFilter::class, 'nonEmpty'])
            )
            ->addFilter(
                'supplier',
                (new StringFilter())
                    ->addCallback([StringFilter::class, 'nonEmpty'])
            )
            ->addFilter('version', new StringFilter());
    }
}
