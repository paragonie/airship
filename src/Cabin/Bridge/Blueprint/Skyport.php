<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Blueprint;

use Airship\Engine\Continuum\Version;

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
    /**
     * Get the number of packages available
     *
     * @return int
     */
    public function countAvailable(string $type = ''): int
    {
        if ($type) {
            $extra  = ' AND packagetype = ?';
            $args = [$type];
        } else {
            $extra = '';
            $args = [];
        }
        return (int) $this->db->cell('
            SELECT
                count(packageid)
            FROM
                airship_package_cache
            WHERE
                NOT installed ' . $extra,
            ...$args
        );
    }

    /**
     * Get the number of packages installed
     *
     * @return int
     */
    public function countInstalled(): int
    {
        return (int) $this->db->cell(
            'SELECT count(packageid) FROM airship_package_cache WHERE installed'
        );
    }

    /**
     * @return int
     */
    public function countOutdated(): int
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
        ');
        $count = 0;
        foreach ($exts as $ext) {
            $available = $this->getAvailableUpgrades($ext);
            if (!empty($available)) {
                ++$count;
            }
        }
        return $count;
    }


    /**
     * @param string $type
     * @param int $offset
     * @param int $limit
     * @return array
     */
    public function getAvailable(
        string $type = '',
        string $query = '',
        int $offset = 0,
        int $limit
    ): array {
        $extra = '';
        $args = [];

        if (!empty($type)) {
            $extra = ' AND packagetype = ?';
            $args = [$type];
        }
        // Search query -- very naive
        if (!empty($query)) {
            $query = '%' . \trim($query, '%') . '%';
            switch ($this->db->getDriver()) {
                case 'pgsql':
                    $extra .= " AND (
                           name LIKE ?
                        OR supplier LIKE ?
                        OR skyport_metadata->>'description' LIKE ?
                        OR skyport_metadata->>'details' LIKE ?
                        OR skyport_metadata->>'version_control' LIKE ?
                    )";
                    $args []= $query;
                    $args []= $query;
                    $args []= $query;
                    $args []= $query;
                    $args []= $query;
                break;
            }
        }

        $exts = $this->db->run('
            SELECT
                *
            FROM
                airship_package_cache
            WHERE
                NOT installed ' . $extra . '
            ORDER BY
                packagetype ASC,
                supplier ASC,
                name ASC
            OFFSET ' . $offset . '
            LIMIT ' . $limit,
            ...$args
        );
        if (empty($exts)) {
            return [];
        }
        return $exts;
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

    /**
     * @return array
     */
    public function getLeftMenu()
    {
        return [
            'needs_update' => $this->countOutdated()
        ];
    }

    /**
     * Gets all packages for which a new version is available.
     */
    public function getOutdatedPackages()
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
        ');
        $outdated = [];
        foreach ($exts as $ext) {
            $available = $this->getAvailableUpgrades($ext);
            if (!empty($available)) {
                $outdated[] = $available;
            }
        }
        return $outdated;
    }

    /**
     * Get a list of all the available upgrade version identifiers
     *
     * @param array $ext
     * @return array
     */
    protected function getAvailableUpgrades(array $ext = []): array
    {
        $current = $this->db->cell(
            'SELECT versionid FROM airship_package_versions WHERE package = ? AND version = ?',
            $ext['packageid'],
            $ext['current_version']
        );
        $available = $this->db->first(
            'SELECT version FROM airship_package_versions WHERE package = ? AND versionid > ?',
            $ext['packageid'],
            $current
        );
        $version = new Version($ext['current_version']);
        $results = [];
        foreach ($available as $ver) {
            if ($version->isUpgrade($ver)) {
                $results []= $ver;
            }
        }
        $ext['upgrades'] = $results;
        return $ext;
    }
}
