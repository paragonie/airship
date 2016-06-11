<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Landing;

use \Airship\Cabin\Bridge\Blueprint\Skyport as SkyportBP;

require_once __DIR__.'/init_gear.php';

/**
 * Class Skyport
 * @package Airship\Cabin\Bridge\Landing
 */
class Skyport extends AdminOnly
{
    /**
     * @var int
     */
    protected $perPage = 10;

    /**
     * @var SkyportBP
     */
    protected $skyport;

    public function airshipLand()
    {
        parent::airshipLand();
        $this->skyport = $this->blueprint('Skyport');
    }

    /**
     * @route ajax/admin/skyport/browse
     */
    public function ajaxGetAvailablePackages()
    {

    }

    /**
     * @route ajax/admin/skyport/installed
     */
    public function ajaxGetInstalledPackages()
    {
        if (IDE_HACKS) {
            $this->skyport = new SkyportBP();
        }
        $numInstalled = $this->skyport->countInstalled();
        list($page, $offset) = $this->getPaginated($numInstalled);
        $this->lens(
            'skyport/list',
            [
                'headline' => 'Installed Extensions',
                'extensions' => $this->skyport->getInstalled(false, $offset, $this->perPage),
                'pagination' => [
                    'count' => $numInstalled,
                    'page' => $page,
                    'per_page' => $this->perPage
                ]
            ]
        );
    }

    /**
     * @route ajax/admin/skyport/stale
     */
    public function ajaxGetOutdatedPackages()
    {

    }

    /**
     * @route ajax/admin/skyport/view
     */
    public function ajaxViewPackageInfo()
    {

    }

    /**
     * @route admin/skyport
     */
    public function index()
    {
        $this->lens('skyport');
    }

    /**
     *
     * @param int $numInstalled
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