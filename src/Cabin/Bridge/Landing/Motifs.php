<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Landing;

use Airship\Cabin\Bridge\Filter\MotifsFilter;
use Airship\Engine\Security\Util;

require_once __DIR__.'/init_gear.php';

/**
 * Class Motifs
 * @package Airship\Cabin\Bridge\Landing
 */
class Motifs extends AdminOnly
{
    /**
     * This function is called after the dependencies have been injected by
     * AutoPilot. Think of it as a user-land constructor.
     */
    public function airshipLand()
    {
        parent::airshipLand();
        $this->storeLensVar('active_submenu', ['Admin', 'Extensions']);
        $this->storeLensVar('active_link', 'bridge-link-admin-ext-motifs');
    }

    /**
     * @route motifs
     */
    public function index()
    {
        $this->lens(
            'motifs',
            [
                'cabins' => $this->getCabinNames()
            ]
        );
    }

    /**
     * @route motifs/{string}
     *
     * @param string $cabinName
     */
    public function manage(string $cabinName = '')
    {
        $cabins = $this->getCabinNamespaces();
        if (!\in_array($cabinName, $cabins)) {
            \Airship\redirect($this->airship_cabin_prefix . '/motifs');
        }
        if (!$this->can('update')) {
            \Airship\redirect($this->airship_cabin_prefix . '/motifs');
        }
        $motifs = \Airship\loadJSON(
            ROOT . '/Cabin/' . $cabinName . '/config/motifs.json'
        );
        $post = $this->post(MotifsFilter::fromConfig(\array_keys($motifs)));
        if ($post) {
            if ($this->updateMotifs($motifs, $post, $cabinName)) {
                \Airship\clear_cache();
                \Airship\redirect(
                    $this->airship_cabin_prefix . '/motifs/cabin/' . $cabinName
                );
            }
        }
        
        $this->lens(
            'motif_manage',
            [
                'cabin_name' => $cabinName,
                'cabins' => $cabins,
                'motifs' => $motifs,
                'title' => \__('Motifs for %s', 'default', Util::noHTML($cabinName))
            ]
        );
    }

    /**
     * @param array $motifs
     * @param array $post
     * @param string $cabin
     * @return bool
     */
    protected function updateMotifs(
        array $motifs,
        array $post,
        string $cabin
    ): bool {
        foreach ($motifs as $i => $motif) {
            $motifs[$i]['enabled'] = !empty($post['motifs_enabled']);
        }
        return \Airship\saveJSON(
            ROOT . '/Cabin/' . $cabin . '/config/motifs.json',
            $motifs
        );
    }
}