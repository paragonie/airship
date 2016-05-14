<?php
declare(strict_types=1);
namespace Airship\Engine\Bolt;

use \Airship\Alerts\CabinNotFound;
use \Airship\Engine\State;

/**
 * Trait Common
 *
 * Common stuff.
 *
 * @package Airship\Engine\Bolt
 */
trait Common
{
    /**
     * Get an array of the Cabin names
     *
     * @return string[]
     */
    public function getCabinNames(): array
    {
        $state = State::instance();
        $cabins = [];
        foreach ($state->cabins as $cabin) {
            $cabins [] = $cabin['name'];
        }
        return $cabins;
    }

    /**
     * Given a URL, return the cabin name that applies to it;
     * otherwise, throw a CabinNotFound exception.
     *
     * @param string $url
     * @return string
     * @throws CabinNotFound
     */
    public function getCabinNameFromURL(string $url): string
    {
        $state = State::instance();
        $ap = $state->autoPilot;
        $cabin = $ap->testCabinForUrl($url);
        if (empty($cabin)) {
            throw new CabinNotFound();
        }
        return $cabin;
    }
}
