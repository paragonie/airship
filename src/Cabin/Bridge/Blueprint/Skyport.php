<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Blueprint;

use Airship\Alerts\Hail\NoAPIResponse;
use Airship\Engine\{
    Continuum\Version,
    Hail,
    Security\HiddenString,
    State
};
use \GuzzleHttp\Client;
use ParagonIE\Halite\Asymmetric\SignaturePublicKey;
use \ParagonIE\Halite\Password;

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
     * @var string
     */
    protected $installHash;

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
        foreach ($exts as $i => $ext) {
            $exts[$i]['skyport_metadata'] = \json_decode($ext['skyport_metadata'], true);
        }
        return $exts;
    }

    /**
     * Get the relevant details for a particular package
     *
     * @param string $type
     * @param string $supplier
     * @param string $name
     * @return array
     */
    public function getDetails(string $type, string $supplier, string $name): array
    {
        $package = $this->db->row(
            'SELECT 
                 *
             FROM
                 airship_package_cache
             WHERE
                     packagetype = ?
                 AND supplier = ?
                 AND name = ?
            ',
            $type,
            $supplier,
            $name
        );
        if (empty($package)) {
            return [];
        }
        $package['skyport_metadata'] = \json_decode($package['skyport_metadata'], true);
        $package['versions'] = $this->getNewVersions($package);
        return $package;
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
     * @param string $type
     * @param string $supplier
     * @param string $name
     * @return string
     */
    public function getURL(string $type, string $supplier, string $name): string
    {
        /**
         * @todo Make this less hard-coded
         */
        $prefix = 'https://airship.paragonie.com/';
        switch (\strtolower($type)) {
            case 'cabin':
                return $prefix . 'cabin/' . $supplier . '/' . $name;
            case 'gadget':
                return $prefix . 'gadget/' . $supplier . '/' . $name;
            case 'motif':
                return $prefix . 'motif/' . $supplier . '/' . $name;
            default:
                return $prefix;
        }
    }

    /**
     * @return bool
     */
    public function isLocked(): bool
    {
        if (!empty($_SESSION['airship_install_lock_override'])) {
            // Previously entered a password to override the lock
            return false;
        }
        return \file_exists(ROOT . '/config/install.lock');
    }

    /**
     * @return bool
     */
    public function isPasswordLocked(): bool
    {
        if (!$this->isLocked()) {
            return false;
        }
        $this->installHash = \file_get_contents(ROOT . '/config/install.lock');
        if (\preg_match('/^3142[0-9a-f]{300,}$/', $this->installHash)) {
            return true;
        }
        return false;
    }

    /**
     * Manually refresh a package's metadata.
     *
     * @param string $type
     * @param string $supplier
     * @param string $pkg
     * @return bool
     */
    public function manualRefresh(
        string $type,
        string $supplier,
        string $pkg
    ): bool {
        $metadata = $this->getPackageMetadata($type, $supplier, $pkg);
        if (empty($metadata)) {
            return false;
        }
        $this->db->beginTransaction();
        $this->db->update(
            'airship_package_cache',
            [
                'skyport_metadata' => \json_encode($metadata[0]['metadata'])
            ],
            [
                'packagetype' => $type,
                'supplier' => $supplier,
                'name' => $pkg
            ]
        );
        return $this->db->commit();
    }

    /**
     * @param HiddenString $password
     * @return bool
     */
    public function tryUnlockPassword(HiddenString $password): bool
    {
        $state = State::instance();
        return Password::verify(
            $password->getString(),
            $this->installHash,
            $state->keyring['auth.password_key']
        );
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
        if (empty($available)) {
            return [];
        }
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

    /**
     * Get all of the new versions available for a given package
     *
     * Unlike getAvailableUpgrades(), this gets the full dataset
     *
     * @param array $package
     * @return array
     */
    protected function getNewVersions(array $package): array
    {
        $current = $this->db->cell(
            'SELECT versionid FROM airship_package_versions WHERE package = ? AND version = ?',
            $package['packageid'],
            $package['current_version']
        );
        $available = $this->db->run(
            'SELECT * FROM airship_package_versions WHERE package = ? AND versionid > ?',
            $package['packageid'],
            $current
        );
        $version = new Version($package['current_version']);
        $results = [];
        foreach ($available as $ver) {
            if ($version->isUpgrade($ver['version'])) {
                $results []= $ver;
            }
        }
        return $results;
    }

    /**
     * Get the updated metadata for a particular package.
     *
     * @param string $type
     * @param string $supplier
     * @param string $pkg
     * @return array
     */
    protected function getPackageMetadata(
        string $type,
        string $supplier,
        string $pkg
    ): array {
        $state = State::instance();
        if (IDE_HACKS) {
            $state->hail = new Hail(new Client());
        }

        $channels = \Airship\loadJSON(ROOT . "/config/channels.json");
        $ch = $state->universal['airship']['trusted-supplier'] ?? 'paragonie';
        if (empty($channels[$ch])) {
            return [];
        }
        $publicKey = new SignaturePublicKey(
            \Sodium\hex2bin($channels[$ch]['publickey'])
        );

        foreach ($channels[$ch]['urls'] as $url) {
            try {
                $response = $state->hail->postSignedJSON(
                    $url,
                    $publicKey,
                    [
                        'type' => $type,
                        'supplier' => $supplier,
                        'name' => $pkg
                    ]
                );
            } catch (NoAPIResponse $ex) {
                // Continue
            }
        }
        if (empty($response)) {
            return [];
        }
        if ($response['status'] !== 'success') {
            return [];
        }
        return $response['packageMetadata'];
    }
}
