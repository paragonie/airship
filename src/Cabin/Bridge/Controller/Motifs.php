<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Controller;

use Airship\Cabin\Bridge\Filter\MotifsFilter;
use Airship\Engine\Gadgets;
use Airship\Engine\Security\Filter\{
    GeneralFilterContainer,
    InputFilterContainer
};
use Airship\Engine\Security\Util;

require_once __DIR__.'/init_gear.php';

/**
 * Class Motifs
 * @package Airship\Cabin\Bridge\Controller
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
        $this->storeViewVar('active_submenu', ['Admin', 'Extensions']);
        $this->storeViewVar('active_link', 'bridge-link-admin-ext-motifs');
    }

    /**
     * @route motif-config/{string}
     *
     * @param string $motifName
     */
    public function configure(string $motifName)
    {
        $motifs = $this->getAllMotifs();
        if (!\array_key_exists($motifName, $motifs)) {
            \Airship\redirect($this->airship_cabin_prefix . '/motifs');
        }
        if (!$this->can('update')) {
            \Airship\redirect($this->airship_cabin_prefix . '/motifs');
        }
        $selected = $motifs[$motifName];
        $path = ROOT . '/Motifs/' . $selected['supplier'] . '/' . $selected['name'];

        // Should we load overload the configuration lens?
        if (\file_exists($path . '/lens/config.twig')) {
            Gadgets::loadCargo(
                'bridge_motifs_config_overloaded',
                'motif/' . $motifName . '/config.twig'
            );
        }
        $inputFilter = null;
        if (\file_exists($path . '/config_filter.php')) {
            $inputFilter = $this->getConfigFilter($path . '/config_filter.php');
        }
        try {
            $motifConfig = \Airship\loadJSON(
                ROOT . '/config/motifs/' . $motifName . '.json'
            );
        } catch (\Throwable $ex) {
            $motifConfig = [];
        }

        // Handle POST data
        if ($inputFilter instanceof InputFilterContainer) {
            $post = $this->post($inputFilter);
        } else {
            $post = $this->post();
            if (\is_string($post['motif_config'])) {
                $post['motif_config'] = \Airship\parseJSON(
                    $post['motif_config'],
                    true
                );
            }
        }
        if ($post) {
            if (empty($post['motif_config'])) {
                $post['motif_config'] = [];
            }
            if ($this->saveMotifConfig(
                $motifName,
                $post['motif_config']
            )) {
                \Airship\redirect(
                    $this->airship_cabin_prefix . '/motif_config/' . $motifName
                );
            }
        }
        $this->view(
            'motif_configure',
            [
                'cabin_name' => $motifName,
                'motifs' => $motifs,
                'motif_config' => $motifConfig,
                'title' => \__('Configuring %s/%s', 'default',
                    Util::noHTML($selected['supplier']),
                    Util::noHTML($selected['name'])
                )
            ]
        );
    }

    /**
     * @route motifs
     */
    public function index()
    {
        $this->view(
            'motifs',
            [
                'cabins' => $this->getCabinNames(),
                'motifs' => $this->getAllMotifs(true)
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
        
        $this->view(
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
     * @param bool $deepSort
     * @return array
     */
    protected function getAllMotifs(bool $deepSort = false): array
    {
        $allMotifs = [];
        $cabins = $this->getCabinNamespaces();
        foreach ($cabins as $cabinName) {
            $motifs = \Airship\loadJSON(ROOT . '/Cabin/' . $cabinName . '/config/motifs.json');
            foreach ($motifs as $motif => $config) {
                if (!\array_key_exists($motif, $allMotifs)) {
                    $allMotifs[$motif] = $config;
                    $allMotifs[$motif]['link'] = $motif;
                }
            }
        }
        if ($deepSort) {
            \usort(
                $allMotifs,
                function (array $a, array $b): int {
                    if ($a['supplier'] === $b['supplier']) {
                        return (int)($a['name'] <=> $b['name']);
                    }
                    return (int)($a['supplier'] <=> $b['supplier']);
                }
            );
        } else {
            \ksort($allMotifs);
        }
        return $allMotifs;
    }

    /**
     * Get an input filter container
     *
     * @param string $path
     * @return InputFilterContainer
     */
    protected function getConfigFilter(string $path): InputFilterContainer
    {
        /** @noinspection PhpIncludeInspection */
        include $path;

        if (!isset($motifInputFilter)) {
            return new GeneralFilterContainer();
        }
        return $motifInputFilter;
    }

    /**
     * @param string $motifName
     * @param array $config
     * @return bool
     */
    protected function saveMotifConfig(
        string $motifName,
        array $config = []
    ): bool {
        $res = \Airship\saveJSON(
            ROOT . '/config/motifs/' . $motifName . '.json',
            $config
        );
        \Airship\clear_cache();
        return $res;
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