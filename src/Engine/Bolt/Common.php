<?php
declare(strict_types=1);
namespace Airship\Engine\Bolt;

use \Airship\Alerts\CabinNotFound;
use \Airship\Engine\{
    AutoPilot,
    State
};

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
            $cabins []= $cabin['name'];
        }
        return $cabins;
    }

    /**
     * Get an array of the Cabin namespaces
     *
     * @return string[]
     */
    public function getCabinNamespaces(): array
    {
        $state = State::instance();
        $cabins = [];
        foreach ($state->cabins as $cabin) {
            $cabins []= $cabin['namespace'] ?? $cabin['name'];
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
        /**
         * @var AutoPilot
         */
        $ap = $state->autoPilot;
        $cabin = $ap->testCabinForUrl($url);
        if (empty($cabin)) {
            throw new CabinNotFound();
        }
        return $cabin;
    }

    /**
     * some-test-user/cabin--for-the-win =>
     * Some_Test_User__Cabin_For_The_Win
     *
     * @param string $supplier
     * @param string $cabin
     * @return string
     */
    public function makeNamespace(string $supplier, string $cabin): string
    {
        $supplier = \preg_replace('/[^A-Za-z0-9_]/', '_', $supplier);
        $exp = \explode('_', $supplier);
        $supplier = \implode('_', \array_map('ucfirst', $exp));
        $supplier = \preg_replace('/_{2,}/', '_', $supplier);

        $cabin = \preg_replace('/[^A-Za-z0-9_]/', '_', $cabin);
        $exp = \explode('_', $cabin);
        $cabin = \implode('_', \array_map('ucfirst', $exp));
        $cabin = \preg_replace('/_{2,}/', '_', $cabin);

        return \implode('__',
            [
                \trim($supplier, '_'),
                \trim($cabin, '_')
            ]
        );
    }
}
