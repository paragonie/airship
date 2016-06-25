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
        $this->lens(
            'gadgets',
            [
                'cabins' => $this->getCabinNames()
            ]
        );
    }

    /**
     * @param string $cabinName
     * @route gadgets/cabin/{string}
     */
    public function manageForCabin(string $cabinName = '')
    {
        $cabins = $this->getCabinNames();

        $this->lens('gadget_manage');
    }

    /**
     * @param string $cabinName
     * @route gadgets/universal
     */
    public function manageUniversal(string $cabinName = '')
    {
        $this->lens('gadget_manage');
    }
}
