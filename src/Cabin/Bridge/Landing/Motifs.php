<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Landing;

require_once __DIR__.'/init_gear.php';

/**
 * Class Motifs
 * @package Airship\Cabin\Bridge\Landing
 */
class Motifs extends AdminOnly
{
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
        $post = $this->post();
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
                'title' => \__('Motifs for %s', 'default', $cabinName)
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