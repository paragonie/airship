<?php
declare(strict_types=1);
namespace Airship\Engine\Bolt;

use \Airship\Engine\State;

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
}
