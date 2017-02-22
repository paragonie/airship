<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Controller;

use Airship\Cabin\Bridge\Model\Skyport as SkyportBP;
use Airship\Cabin\Bridge\Filter\SkyportFilter;
use Airship\Engine\Security\{
    Util
};
use ParagonIE\Halite\HiddenString;
use Psr\Log\LogLevel;

require_once __DIR__.'/init_gear.php';

/**
 * Class Skyport
 * @package Airship\Cabin\Bridge\Controller
 */
class Skyport extends AdminOnly
{
    /**
     * @var string
     */
    protected $channel = 'paragonie';

    /**
     * @var int
     */
    protected $perPage = 10;

    /**
     * @var SkyportBP
     */
    protected $skyport;

    /**
     * This function is called after the dependencies have been injected by
     * AutoPilot. Think of it as a user-land constructor.
     *
     * @throws \TypeError
     */
    public function airshipLand()
    {
        parent::airshipLand();
        $this->skyport = $this->model('Skyport');
        if (!($this->skyport instanceof SkyportBP)) {
            throw new \TypeError(
                \trk('errors.type.wrong_class', SkyportBP::class)
            );
        }
        $this->storeViewVar('active_submenu', ['Admin', 'Extensions']);
        $this->storeViewVar('active_link', 'bridge-link-skyport');
    }

    /**
     * @route ajax/admin/skyport/browse
     */
    public function ajaxGetAvailablePackages()
    {
        $post = $_POST ?? [];
        $type = '';
        $headline = 'Available Extensions';
        if (isset($post['type'])) {
            switch ($post['type']) {
                case 'cabin':
                    $headline = 'Available Cabins';
                    $type = 'Cabin';
                    break;
                case 'gadget':
                    $headline = 'Available Gadgets';
                    $type = 'Gadget';
                    break;
                case 'motif':
                    $headline = 'Available Motifs';
                    $type = 'Motif';
                    break;
            }
        }
        $query = (string) ($post['query'] ?? '');
        $numAvailable = $this->skyport->countAvailable($type);
        list($page, $offset) = $this->getPaginated($numAvailable);

        $this->lens(
            'skyport/list',
            [
                'headline' => $headline,
                'extensions' => $this->skyport->getAvailable(
                        $type,
                        $query,
                        $offset,
                        $this->perPage
                    ),
                'pagination' => [
                    'count' => $numAvailable,
                    'page' => $page,
                    'per_page' => $this->perPage
                ]
            ]
        );
    }

    /**
     * @route ajax/admin/skyport/installed
     */
    public function ajaxGetInstalledPackages()
    {
        $numInstalled = $this->skyport->countInstalled();
        list($page, $offset) = $this->getPaginated($numInstalled);
        $this->lens(
            'skyport/list',
            [
                'headline' => 'Installed Extensions',
                'extensions' => $this->skyport->getInstalled(
                    false,
                    $offset,
                    $this->perPage
                ),
                'pagination' => [
                    'count' => $numInstalled,
                    'page' => $page,
                    'per_page' => $this->perPage
                ]
            ]
        );
    }

    /**
     * @route ajax/admin/skyport/leftmenu
     */
    public function ajaxGetLeftMenu()
    {
        $this->lens(
            'skyport/left',
            [
                'left' => $this->skyport->getLeftMenu()
            ]
        );
    }

    /**
     * @route ajax/admin/skyport/stale
     */
    public function ajaxGetOutdatedPackages()
    {
        $this->lens(
            'skyport/outdated',
            [
                'headline' => 'Outdated Extensions',
                'extensions' => $this->skyport->getOutdatedPackages()
            ]
        );
    }

    /**
     * @route ajax/admin/skyport/refresh
     */
    public function ajaxRefreshPackageInfo()
    {
        $expected = [
            'package',
            'supplier',
            'type'
        ];
        if (!\Airship\all_keys_exist($expected, $_POST)) {
            echo 'Invalid POST request.', "\n";
            return;
        }
        $post = $_POST ?? [];
        $type = '';
        if (isset($post['type'])) {
            switch ($post['type']) {
                case 'cabin':
                    $type = 'Cabin';
                    break;
                case 'gadget':
                    $type = 'Gadget';
                    break;
                case 'motif':
                    $type = 'Motif';
                    break;
                default:
                    echo 'Invalid POST request.', "\n";
                    return;
            }
        }

        $this->skyport->manualRefresh(
            $type,
            $_POST['supplier'],
            $_POST['package']
        );

        $this->lens(
            'skyport/view',
            [
                'package' => $this->skyport->getDetails(
                    $_POST['type'],
                    $_POST['supplier'],
                    $_POST['package']
                ),
                'skyport_url' => $this->skyport->getURL(
                    $_POST['type'],
                    $_POST['supplier'],
                    $_POST['package']
                )
            ]
        );
    }


    /**
     * @route ajax/admin/skyport/view
     */
    public function ajaxViewPackageInfo()
    {
        $expected = [
            'package',
            'supplier',
            'type'
        ];
        if (!\Airship\all_keys_exist($expected, $_POST)) {
            echo 'Invalid POST request.', "\n";
            return;
        }

        $this->lens(
            'skyport/view',
            [
                'package' => $this->skyport->getDetails(
                    $_POST['type'],
                    $_POST['supplier'],
                    $_POST['package']
                ),
                'skyport_url' => $this->skyport->getURL(
                    $_POST['type'],
                    $_POST['supplier'],
                    $_POST['package']
                )
            ]
        );
    }

    /**
     * @route admin/skyport
     */
    public function index()
    {
        $this->lens(
            'skyport',
            [
                'left' => $this->skyport->getLeftMenu()
            ]
        );
    }

    /**
     * Trigger the package install process
     *
     */
    public function installPackage()
    {
        $expected = [
            'package',
            'supplier',
            'type'
        ];
        if (!\Airship\all_keys_exist($expected, $_POST)) {
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => \__('Incomplete request.')
            ]);
        }
        if ($this->skyport->isLocked()) {
            $locked = true;
            if ($this->skyport->isPasswordLocked() && !empty($_POST['password'])) {
                $password = new HiddenString($_POST['password']);
                if ($this->skyport->tryUnlockPassword($password)) {
                    $_SESSION['airship_install_lock_override'] = true;
                    $locked = false;
                }
                unset($password);
            }
            if ($locked) {
                if ($this->skyport->isPasswordLocked()) {
                    \Airship\json_response([
                        'status' => 'PROMPT',
                        'message' => \__(
                            'The skyport is locked. To unlock the skyport, please provide the password.'
                        )
                    ]);
                }
                \Airship\json_response([
                    'status' => 'ERROR',
                    'message' => \__(
                        'The skyport is locked. You cannot install packages from the web interface.'
                    )
                ]);
            }
        }
        try {
            $filter = new SkyportFilter();
            $_POST = $filter($_POST);
        } catch (\TypeError $ex) {
            $this->log(
                "Input violation",
                LogLevel::ALERT,
                \Airship\throwableToArray($ex)
            );
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => \__(
                    'Invalid input.'
                )
            ]);
        }

        /**
         * @security We need to guarantee RCE isn't possible:
         */
        $args = \implode(
            ' ',
            [
                \escapeshellarg(
                    Util::charWhitelist($_POST['type'], Util::PRINTABLE_ASCII)
                ),
                \escapeshellarg(
                    Util::charWhitelist($_POST['supplier'], Util::PRINTABLE_ASCII) .
                        '/' .
                    Util::charWhitelist($_POST['package'], Util::PRINTABLE_ASCII)
                )
            ]
        );
        $output = \shell_exec('php -dphar.readonly=0 ' . ROOT . '/CommandLine/install.sh ' . $args);

        \Airship\json_response([
            'status' => 'OK',
            'message' => $output
        ]);
    }

    /**
     * Trigger the package uninstall process.
     */
    public function removePackage()
    {
        $expected = [
            'package',
            'supplier',
            'type'
        ];
        if (!\Airship\all_keys_exist($expected, $_POST)) {
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => \__('Incomplete request.')
            ]);
        }
        if ($this->skyport->isLocked()) {
            $locked = true;
            if ($this->skyport->isPasswordLocked() && !empty($_POST['password'])) {
                $password = new HiddenString($_POST['password']);
                if ($this->skyport->tryUnlockPassword($password)) {
                    $_SESSION['airship_install_lock_override'] = true;
                    $locked = false;
                }
                unset($password);
            }
            if ($locked) {
                if ($this->skyport->isPasswordLocked()) {
                    \Airship\json_response([
                        'status' => 'PROMPT',
                        'message' => \__(
                            'The skyport is locked. To unlock the skyport, please provide the password.'
                        )
                    ]);
                }
                \Airship\json_response([
                    'status' => 'ERROR',
                    'message' => \__(
                        'The skyport is locked. You cannot install packages from the web interface.'
                    )
                ]);
            }
        }

        try {
            $filter = new SkyportFilter();
            $post = $filter($_POST);
        } catch (\TypeError $ex) {
            $this->log(
                "Input violation",
                LogLevel::ALERT,
                \Airship\throwableToArray($ex)
            );
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => 'Type Error'
            ]);
            return;
        }

        $output = $this->skyport->forceRemoval(
            $post['type'],
            $post['supplier'],
            $post['package']
        );

        \Airship\json_response([
            'status' => 'OK',
            'message' => $output
        ]);
    }

    /**
     * Trigger the package install process
     */
    public function updatePackage()
    {
        $expected = [
            'package',
            'supplier',
            'type',
            'version'
        ];
        if (!\Airship\all_keys_exist($expected, $_POST)) {
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => \__('Incomplete request.')
            ]);
        }
        try {
            $filter = new SkyportFilter();
            $_POST = $filter($_POST);
        } catch (\TypeError $ex) {
            $this->log(
                "Input violation",
                LogLevel::ALERT,
                \Airship\throwableToArray($ex)
            );
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => \__(
                    'Invalid input.'
                )
            ]);
        }

        /**
         * @security We need to guarantee RCE isn't possible:
         */
        $args = \implode(
            ' ',
            [
                \escapeshellarg(
                    Util::charWhitelist($_POST['type'], Util::PRINTABLE_ASCII)
                ),
                \escapeshellarg(
                    Util::charWhitelist($_POST['supplier'], Util::PRINTABLE_ASCII) .
                        '/' .
                    Util::charWhitelist($_POST['package'], Util::PRINTABLE_ASCII)
                ),
                \escapeshellarg(
                    Util::charWhitelist($_POST['version'], Util::PRINTABLE_ASCII)
                )
            ]
        );
        $output = \shell_exec(
            'php -dphar.readonly=0 ' . ROOT . '/CommandLine/update_one.sh ' . $args
        );

        \Airship\json_response([
            'status' => 'OK',
            'message' => $output
        ]);
    }

    /**
     * View the update log
     *
     * @route admin/skyport/log
     */
    public function viewLog()
    {
        /** @todo allow a more granular window of logged events to be viewed */
        $this->lens(
            'skyport/log',
            [
                'active_link' =>
                    'bridge-link-admin-ext-log',
                'logged' =>
                    $this->skyport->getLogMessages()
            ]
        );
    }

    /**
     * Get the page number and offset
     *
     * @param int $sizeOfList
     * @return int[]
     */
    protected function getPaginated(int $sizeOfList): array
    {
        $page = (int) ($_POST['page'] ?? 1);
        if ((($page - 1) * $this->perPage) > $sizeOfList) {
            $page = 1;
        }
        if ($page < 1) {
            $page = 1;
        }
        return [
            (int) $page,
            (int) ($page - 1) * $this->perPage
        ];
    }
}