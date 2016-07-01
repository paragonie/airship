<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Landing;

use \Airship\Cabin\Bridge\Filter\GadgetsFilter;
use \Airship\Engine\Security\Util;

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
                'cabins' => $this->getCabinNamespaces()
            ]
        );
    }

    /**
     * @param string $cabinName
     * @route gadgets/cabin/{string}
     */
    public function manageForCabin(string $cabinName = '')
    {
        $cabins = $this->getCabinNamespaces();
        if (!\in_array($cabinName, $cabins)) {
            \Airship\redirect($this->airship_cabin_prefix . '/gadgets');
        }
        if (!$this->can('update')) {
            \Airship\redirect($this->airship_cabin_prefix . '/gadgets');
        }
        $gadgets = \Airship\loadJSON(
            ROOT . '/Cabin/' . $cabinName . '/config/gadgets.json'
        );
        $post = $this->post(GadgetsFilter::fromConfig(\array_keys($gadgets)));
        if ($post) {
            if ($this->updateCabinGadgets($gadgets, $post, $cabinName)) {
                \Airship\clear_cache();
                \Airship\redirect(
                    $this->airship_cabin_prefix . '/gadgets/cabin/' . $cabinName
                );
            }
        }

        $this->lens(
            'gadget_manage',
            [
                'cabins' => $cabins,
                'gadgets' => $gadgets,
                'title' => \__('Gadgets for %s', 'default', Util::noHTML($cabinName))
            ]
        );
    }

    /**
     * @param string $cabinName
     * @route gadgets/universal
     */
    public function manageUniversal(string $cabinName = '')
    {
        $cabins = $this->getCabinNamespaces();
        $gadgets = \Airship\loadJSON(ROOT . '/config/gadgets.json');
        if (!$this->can('update')) {
            \Airship\redirect($this->airship_cabin_prefix . '/gadgets');
        }
        $post = $this->post(GadgetsFilter::fromConfig(\array_keys($gadgets)));
        if ($post) {
            if ($this->updateUniversalGadgets($gadgets, $post)) {
                \Airship\clear_cache();
                \Airship\redirect(
                    $this->airship_cabin_prefix . '/gadgets/universal'
                );
            }
        }

        $this->lens(
            'gadget_manage',
            [
                'cabins' => $cabins,
                'gadgets' => $gadgets,
                'title' => \__('Manage Universal Gadgets')
            ]
        );
    }

    /**
     * Update the gadgets for a given Cabin
     *
     * @param array $gadgets
     * @param array $post
     * @param string $cabin
     * @return bool
     */
    protected function updateCabinGadgets(
        array $gadgets,
        array $post,
        string $cabin = ''
    ): bool {
        $sortedGadgets = [];
        foreach (\array_unique($post['gadget_order']) as $i => $index) {
            $gadgets[$index]['enabled'] = !empty($post['gadget_enabled'][$index]);
            $sortedGadgets []= $gadgets[$index];
            unset($gadgets[$index]);
        }
        // Just in case any were omitted
        foreach ($gadgets as $gadget) {
            $gadget['enabled'] = false;
            $sortedGadgets []= $gadget;
        }
        return \Airship\saveJSON(
            ROOT . '/Cabin/' . $cabin . '/config/gadgets.json',
            $sortedGadgets
        );
    }

    /**
     * Update the universal gadgets
     *
     * @param array $gadgets
     * @param array $post
     * @return bool
     */
    protected function updateUniversalGadgets(
        array $gadgets,
        array $post
    ): bool {
        $sortedGadgets = [];
        foreach (\array_unique($post['gadget_order']) as $i => $index) {
            $gadgets[$index]['enabled'] = !empty($post['gadget_enabled'][$index]);
            $sortedGadgets []= $gadgets[$index];
            unset($gadgets[$index]);
        }
        // Just in case any were omitted
        foreach ($gadgets as $gadget) {
            $gadget['enabled'] = false;
            $sortedGadgets []= $gadget;
        }
        return \Airship\saveJSON(
            ROOT . '/config/gadgets.json',
            $sortedGadgets
        );
    }
}
