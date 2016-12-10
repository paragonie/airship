<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Blueprint;

use Airship\Alerts\Hail\NoAPIResponse;
use Airship\Engine\{
    Bolt\Common,
    Continuum\Version,
    Hail,
    State
};
use ParagonIE\Halite\{
    Asymmetric\SignaturePublicKey,
    HiddenString,
    Password
};

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
    use Common;

    /**
     * @var string
     */
    protected $installHash;

    /**
     * @var string
     */
    protected $prefix = 'https://airship.paragonie.com/';

    /**
     * Get the number of packages available
     *
     * @param string $type The package type
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
     * Force a package to be removed.
     *
     * @param string $type
     * @param string $supplier
     * @param string $package
     * @return string
     * @throws \Exception
     */
    public function forceRemoval(
        string $type,
        string $supplier,
        string $package
    ): string {
        $packageInfo = $this->getDetails($type, $supplier, $package);
        if (empty($packageInfo)) {
            throw new \Exception(
                \__('Package not found!')
            );
        }
        if (!$packageInfo['installed']) {
            return 'Package is not installed.';
        }
        $return = "Package {$supplier}/{$package} ({$type}) found." .
            " Proceeding with removal.\n";

        switch ($type) {
            case 'Cabin':
                $return .= $this->removeCabin($packageInfo);
                break;
            case 'Gadget':
                $return .= $this->removeGadget($packageInfo);
                break;
            case 'Motif':
                $return .= $this->removeMotif($packageInfo);
                break;
        }
        $this->db->beginTransaction();
        $this->db->update(
            'airship_package_cache',
            [
                'installed' => false
            ],
            [
                'packageid' => $packageInfo['packageid']
            ]
        );
        if ($this->db->commit()) {
            $return .= "Removed successfully.";
        }

        return $return;
    }

    /**
     * Get the available packages (based on data provided by Keyggdrasil)
     *
     * @param string $type
     * @param string $query Search query
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
            if (empty($exts[$i]['skyport_metadata'])) {
                $exts[$i]['skyport_metadata'] = [];
            } else {
                $exts[$i]['skyport_metadata'] = \json_decode(
                    $ext['skyport_metadata'],
                    true
                );
            }
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
    public function getDetails(
        string $type,
        string $supplier,
        string $name
    ): array {
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
        if (empty($package['skyport_metadata'])) {
            $package['skyport_metadata'] = [];
        } else {
            $package['skyport_metadata'] = \json_decode(
                $package['skyport_metadata'],
                true
            );
        }
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
    public function getInstalled(
        bool $grouped = false,
        int $offset = 0,
        int $limit = 20
    ): array {
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
     * Dynamic content for the left menu of the Skyport.
     *
     * @return array
     */
    public function getLeftMenu()
    {
        return [
            'needs_update' => $this->countOutdated()
        ];
    }

    /**
     * Get the recent messages in the update log
     *
     * @param \DateTime $cutoffDate
     * @return array
     */
    public function getLogMessages(\DateTime $cutoffDate = null): array
    {
        if (!$cutoffDate) {
            // Default: the past 24 hours
            $cutoffDate = (new \DateTime())->sub(
                new \DateInterval('P01D')
            );
        }
        $messages = $this->db->run(
            "SELECT * FROM airship_continuum_log WHERE created > ? ORDER BY logid DESC",
            $cutoffDate->format('Y-m-d H:i:s')
        );
        if (empty($messages)) {
            return [];
        }
        foreach ($messages as $i => $msg) {
            $messages[$i]['context'] = \json_decode($msg['context'], true);
        }
        return $messages;
    }

    /**
     * Gets all packages for which a new version is available.
     */
    public function getOutdatedPackages(): array
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
        switch (\strtolower($type)) {
            case 'cabin':
                return $this->prefix . 'cabin/' . $supplier . '/' . $name;
            case 'gadget':
                return $this->prefix . 'gadget/' . $supplier . '/' . $name;
            case 'motif':
                return $this->prefix . 'motif/' . $supplier . '/' . $name;
            default:
                return $this->prefix;
        }
    }

    /**
     * Is the skyport locked? This prevents packages from being installed or
     * removed.
     *
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
     * Is the skyport password-protected? This prevents packages from being
     * installed or removed without a separate password?
     *
     * @return bool
     */
    public function isPasswordLocked(): bool
    {
        if (!$this->isLocked()) {
            return false;
        }
        $this->installHash = \file_get_contents(ROOT . '/config/install.lock');
        if (\preg_match('/^3142[0-9a-f]{300,}$/', $this->installHash)) {
            // This looks like an encrypted password hash.
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
        $metadata = $this->getPackageMetadata(
            $type,
            $supplier,
            $pkg
        );
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
            $password,
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
        if (!$current) {
            $current = 0;
        }
        $available = $this->db->run(
            'SELECT * FROM airship_package_versions WHERE package = ? AND versionid > ?',
            $package['packageid'],
            $current
        );
        $version = new Version($package['current_version'] ?? '0.0.0');
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
     *
     * @throws \TypeError
     */
    protected function getPackageMetadata(
        string $type,
        string $supplier,
        string $pkg
    ): array {
        $state = State::instance();
        if (!($state->hail instanceof Hail)) {
            throw new \TypeError(
                \trk('errors.type.wrong_class', Hail::class)
            );
        }

        $channels = \Airship\loadJSON(ROOT . "/config/channels.json");
        $ch = $state->universal['airship']['trusted-supplier'] ?? 'paragonie';
        if (empty($channels[$ch])) {
            return [];
        }
        $publicKey = new SignaturePublicKey(
            new HiddenString(
                \Sodium\hex2bin($channels[$ch]['publickey'])
            )
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

    /**
     * Remove a cabin
     *
     * @param array $info
     * @return string
     */
    protected function removeCabin(array $info): string
    {
        $ret = '';
        $cabins = \Airship\loadJSON(ROOT . '/config/cabins.json');
        $search = $this->makeNamespace($info['supplier'], $info['name']);

        foreach ($cabins as $i => $cabin) {
            if ($cabin['name'] === $search) {
                $ret .= "Removed {$search} from config/cabins.json\n";
                $symlink = ROOT . '/Cabin/Bridge/Lens/cabin_links/' . $search;
                if (\is_link($symlink)) {
                    \unlink($symlink);
                }
                unset($cabins[$i]);
            }
        }
        if (empty($ret)) {
            return 'Cabin not configured or missing.';
        }

        $twigEnv = \Airship\configWriter(ROOT . 'config/templates');
        // Save cabins.json
        \file_put_contents(
            ROOT . '/config/cabins.json',
            $twigEnv->render('cabins.twig', ['cabins' => $cabins])
        );
        /**
         * @security Watch this carefully:
         */
        $ret .= \shell_exec('rm -rf ' . \escapeshellarg(ROOT . '/Cabin/' . $search));

        return $ret;
    }

    /**
     * Remove a gadget
     *
     * @param array $info
     * @return string
     */
    protected function removeGadget(array $info): string
    {
        // Is this in the universal gadgets file?
        $gadgets = \Airship\loadJSON(ROOT . '/config/gadgets.json');
        $found = false;
        foreach ($gadgets as $i => $gadget) {
            if ($gadget['supplier'] === $info['supplier']) {
                if ($gadget['name'] === $info['name']) {
                    $found = true;
                    \unlink(
                        \implode(
                            '/',
                            [
                                ROOT,
                                'Gadgets',
                                $info['supplier'],
                                $info['supplier'] . $info['name'] . '.phar'
                            ]
                        )
                    );
                    unset($gadgets[$i]);
                    break;
                }
            }
        }
        if ($found) {
            \Airship\saveJSON(ROOT . '/config/cabins.json', $gadgets);
            return "Gadget removed from global configuration.\n";
        }
        foreach (\glob(ROOT . '/Cabin/*') as $cabinDir) {
            if (!\is_dir($cabinDir)) {
                continue;
            }
            $cabin = \Airship\path_to_filename($cabinDir);
            $gadgets = \Airship\loadJSON(
                ROOT . '/Cabin/' . $cabin . '/config/gadgets.json'
            );
            $found = false;
            foreach ($gadgets as $i => $gadget) {
                if ($gadget['supplier'] === $info['supplier']) {
                    if ($gadget['name'] === $info['name']) {
                        $found = true;
                        \unlink(
                            \implode(
                                '/',
                                [
                                    ROOT,
                                    'Cabin',
                                    $cabin,
                                    'Gadgets',
                                    $info['supplier'],
                                    $info['supplier'] . $info['name'] . '.phar'
                                ]
                            )
                        );
                        unset($gadgets[$i]);
                        break;
                    }
                }
            }
            if ($found) {
                \Airship\saveJSON(ROOT . '/config/cabins.json', $gadgets);
                return "Gadget removed.\n";
            }
        }
        return "Gadget not found in any configuration.\n";
    }

    /**
     * Remove a motif
     *
     * @param array $info
     * @return string
     */
    protected function removeMotif(array $info): string
    {
        $ret = '';
        foreach (\glob(ROOT . '/Cabin/*') as $cabinDir) {
            if (!\is_dir($cabinDir)) {
                continue;
            }
            $cabin = \Airship\path_to_filename($cabinDir);

            // The cache file has a combined
            $pathInfo = $this->getMotifPath($cabin, $info);
            if (empty($pathInfo)) {
                continue;
            }
            $ret .= "\tRemoving motif from {$cabin}.\n";
            list ($key, $path) = $pathInfo;
            $this->deleteMotifFromCabin($cabin, $key);
            if (\is_dir(ROOT . '/Motifs/' . $path)) {
                $ret .= \shell_exec('rm -rf ' . \escapeshellarg(ROOT . '/Motifs/' . $path));
                \clearstatcache();
            }
            if (\is_link(ROOT . '/Cabin/' . $cabin . '/Lens/motif/' . $key)) {
                \unlink(ROOT . '/Cabin/' . $cabin . '/Lens/motif/' . $key);
            }
        }
        return $ret;
    }

    /**
     * Deletes a motif from a cabin's configuration
     *
     * @param string $cabin
     * @param string $key
     */
    protected function deleteMotifFromCabin(string $cabin, string $key)
    {
        $filename = ROOT . '/Cabin/' . $cabin . '/config/motifs.json';
        $motifs = \Airship\loadJSON($filename);
        if (isset($motifs[$key])) {
            unset($motifs[$key]);
            \Airship\saveJSON($filename, $motifs);
        }
    }

    /**
     * Locate the path that hosts the motif
     *
     * @param string $cabin
     * @param array $info
     * @return string[]
     */
    protected function getMotifPath(string $cabin, array $info): array
    {
        if (\file_exists(ROOT . '/Cabin/' . $cabin . '/config/motifs.json')) {
            $motifs = \Airship\loadJSON(ROOT . '/Cabin/' . $cabin . '/config/motifs.json');
            foreach ($motifs as $path => $motifConfig) {
                if ($motifConfig['supplier'] === $info['supplier']) {
                    if ($motifConfig['name'] === $info['name']) {
                        return [
                            $motifConfig['path'],
                            ROOT . '/Motifs/' . $path
                        ];
                    }
                }
            }
        }
        return [];
    }
}
