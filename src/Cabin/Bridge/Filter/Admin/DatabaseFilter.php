<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\Admin;

use Airship\Engine\Security\Filter\{
    ArrayFilter,
    GeneralFilterContainer,
    IntFilter,
    StringFilter
};

/**
 * Class DatabaseFilter
 * @package Airship\Cabin\Bridge\Filter\Account
 */
class DatabaseFilter extends GeneralFilterContainer
{
    /**
     * Adds filter rules dynamically.
     *
     * @param string $key
     * @param int $index
     * @param int $numEntries
     * @return DatabaseFilter
     */
    public function addDatabaseFilters(string $key, int $index, int $numEntries): self
    {
        for ($i = 0; $i < $numEntries; ++$i) {
            $prefix = $key . '.' . $i . '.';
            $this->addFilter(
                $prefix . 'driver',
                (new StringFilter())
                    ->addCallback([StringFilter::class, 'nonEmpty'])
                )
                ->addFilter(
                    $prefix . 'host',
                    (new StringFilter())
                )
                ->addFilter(
                    $prefix . 'port',
                    (new IntFilter())
                )
                ->addFilter(
                    $prefix . 'username',
                    (new StringFilter())
                )
                ->addFilter(
                    $prefix . 'password',
                    (new StringFilter())
                )
                ->addFilter(
                    $prefix . 'database',
                    (new StringFilter())
                )
                ->addFilter(
                    $prefix . 'options',
                    (new ArrayFilter())
                )
            ;
        }
        return $this;
    }
}
