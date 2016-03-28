<?php
declare(strict_types=1);
namespace Airship\Engine\Bolt;

trait Orderable
{
    /**
     * Parse the standard sorting arguments
     *
     * @param string $defaultIndex
     * @param bool $defaultDesc
     * @return array
     */
    public function getSortArgs(string $defaultIndex, bool $defaultDesc = false): array
    {
        $sort = $_GET['sort'] ?? $defaultIndex;
        $dir = $_GET['dir'] ?? (
            $defaultDesc
                ? 'DESC'
                : 'ASC'
        );
        $dir = \strtoupper($dir);
        if ($dir !== 'ASC' && $dir !== 'DESC') {
            $dir = $defaultDesc
                ? 'DESC'
                : 'ASC';
        }
        return [$sort, $dir];
    }

    /**
     * Create an arbitrary 
     * 
     * @param string $column
     * @param string $direction
     * @param array $whiteList
     * @param string $default
     * @return string
     */
    public function orderBy(
        string $column, 
        string $direction = 'ASC',
        array $whiteList = [],
        string $default = 'name'
    ): string {
        if (!\in_array($column, $whiteList)) {
            $column = $default;
        }
        if ($direction !== 'ASC' && $direction !== 'DESC') {
            $direction = 'ASC';
        }

        /*
        In the future, we may need to switch-case this based on
        $this->db->getDriver()
        */
        return 'ORDER BY ' . $column . ' ' . $direction;
    }

    /**
     * Sort a two-dimensional array by a column
     *
     * @param array $array
     * @param string $sort
     * @param bool $reverse
     * @return bool
     */
    protected function sortArrayByIndex(
        array &$array,
        string $sort = 'name',
        bool $reverse = false
    ): bool {
        if ($reverse) {
            return \uasort($array, function ($a, $b) use ($sort):int {
                return $b[$sort] <=> $a[$sort];
            });
        }
        return \uasort($array, function ($a, $b) use ($sort):int {
            return $a[$sort] <=> $b[$sort];
        });
    }
}