<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Landing;

use \Airship\Cabin\Hull\Exceptions\CustomPageNotFoundException;
use \Airship\Engine\Gears;
use \Airship\Engine\State;
use Psr\Log\LogLevel;
use \ReCaptcha\ReCaptcha;

require_once __DIR__.'/init_gear.php';

/**
 * Class Redirects
 * @package Airship\Cabin\Bridge\Landing
 */
class Redirects extends LoggedInUsersOnly
{
    public function airshipLand()
    {
        parent::airshipLand();
        $this->pg = $this->blueprint('CustomPages');
    }

    /**
     * @route redirects/{string}
     * @param string $cabin
     */
    public function forCabin(string $cabin = '')
    {
        $this->lens(
            'redirect_for_cabin',
            [
                'cabin' => $cabin,
                'redirects' => $this->pg->getRedirectsForCabin($cabin)
            ]
        );
    }

    /**
     * @route redirects
     */
    public function index()
    {
        $this->lens('redirect', [
            'cabins' => $this->getCabinNamespaces()
        ]);
    }

}