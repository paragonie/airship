<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Blueprint;

require_once __DIR__.'/init_gear.php';

/**
 * Class Skyport
 *
 * Manages data about the available/existing Airship extensions
 *
 * @package Airship\Cabin\Bridge\Blueprint
 */
class Skyport extends BlueprintGear
{
    public function countInstalled(): int
    {
        return $this->db->cell(
            'SELECT count(packageid) FROM airship_package_cache WHERE installed'
        );
    }

    /**
     * Get a list of all installed packages
     *
     * @param bool $grouped Group by type? (Creates 2D array)
     * @param int $offset
     * @param int $limit
     * @return array
     */
    public function getInstalled(bool $grouped = false, int $offset = 0, int $limit): array
    {
        $exts = $this->db->run('
            SELECT
                *
            FROM
                airship_package_cache
            WHERE
                installed
            ORDER BY
                packagetype ASC,
                supplier ASC,
                name ASC
            OFFSET ' . $offset . '
            LIMIT ' . $limit);
        if ($grouped) {
            $groups = [
                'Core' => [],
                'Cabin' => [],
                'Gadget' => [],
                'Motif' => []
            ];
            foreach ($exts as $ext) {
                $groups[$ext['packagetype']] = $ext;
            }
            return $groups;
        }
        return $exts;
    }
}
