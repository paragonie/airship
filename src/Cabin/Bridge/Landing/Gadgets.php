<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Landing;

require_once __DIR__.'/init_gear.php';

/**
 * Class Gadgets
 * @package Airship\Cabin\Bridge\Landing
 */
class Gadgets extends LoggedInUsersOnly
{
    /**
     * @route gadgets
     */
    public function index()
    {
        $this->lens('gadgets');
    }

    /**
     * @param string $cabinName
     */
    public function manage(string $cabinName = '')
    {
        $this->lens('gadget_manage');
    }
}
