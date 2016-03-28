<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Landing;

use \Airship\Engine\State;

require_once __DIR__.'/gear.php';

class Skyport extends AdminOnly
{
    /**
     * @route admin/skyport
     */
    public function index()
    {
        $this->lens('skyport');
    }
}