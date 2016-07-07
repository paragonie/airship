<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Landing;

use Airship\Alerts\FileSystem\FileNotFound;

use Airship\Engine\Security\Filter\{
    ArrayFilter,
    GeneralFilterContainer
};

require_once __DIR__.'/init_gear.php';

/**
 * Class Cabins
 * @package Airship\Cabin\Bridge\Landing
 */
class Cabins extends LoggedInUsersOnly
{
    /**
     * @param string $cabinName
     * @route cabin/{string}
     */
    public function cabinMenu(string $cabinName = '')
    {
        if (!$this->isLoggedIn()) {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        if (!$this->can('read')) {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        if (!\in_array($cabinName, $this->getCabinNamespaces())) {
            // Invalid cabin name
            \Airship\redirect($this->airship_cabin_prefix . '/cabins');
        }

        $cabin = \Airship\loadJSON(
            ROOT . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'cabins.json'
        );
        foreach ($cabin as $path => $data) {
            if ($data['name'] === $cabinName) {
                $settings['cabin'] = $data;
                $settings['cabin']['path'] = $path;
                break;
            }
        }
        if (empty($settings['cabin'])) {
            // Cabin not found
            \Airship\redirect($this->airship_cabin_prefix);
        }
        $this->lens('cabins_menu', [
            'name' => $cabinName,
            'config' => $settings
        ]);
    }

    /**
     * @route cabins
     */
    public function index()
    {
        if (!$this->isLoggedIn()) {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        $this->lens('cabins', [
            'cabins' => $this->getCabinNamespaces()
        ]);
    }

    /**
     * Update Cabin configuration
     *
     * @route cabins/manage{_string}
     * @param string $cabinName
     */
    public function manage(string $cabinName = '')
    {
        if (!$this->isSuperUser()) {
            // Admins only!
            \Airship\redirect($this->airship_cabin_prefix);
        }
        if (!\in_array($cabinName, $this->getCabinNamespaces())) {
            // Invalid cabin name
            \Airship\redirect($this->airship_cabin_prefix . '/cabins');
        }
        if (!$this->ensureCabinLinkExists($cabinName)) {
            \Airship\json_response([
                'error' => 'Could not create symlink'
            ]);
        }

        $cabin = \Airship\loadJSON(
            ROOT . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'cabins.json'
        );

        // Apply the cabin's input filter:
        $filterName = '\\Airship\\Cabin\\' . $cabinName . '\\ConfigFilter';
        if (\class_exists($filterName)) {
            $filter = new $filterName;
        } else {
            $filter = (new GeneralFilterContainer())
                ->addFilter('content_security_policy', new ArrayFilter());
        }
        $post = $this->post($filter);
        if (!empty($post)) {
            if ($this->saveSettings($cabinName, $cabin, $post)) {
                \Airship\redirect(
                    $this->airship_cabin_prefix . '/cabins/manage/' . $cabinName
                );
            }
        }

        $settings = [];
        foreach ($cabin as $path => $data) {
            if ($data['name'] === $cabinName) {
                $settings['cabin'] = $data;
                $settings['cabin']['path'] = $path;
                break;
            }
        }
        if (empty($settings['cabin'])) {
            // Cabin not found
            \Airship\redirect($this->airship_cabin_prefix);
        }
        $settings['content_security_policy'] = $this->loadJSONConfigFile(
            $cabinName,
            'content_security_policy.json'
        );
        $settings['cabin_extra'] = \Airship\loadJSON(
            ROOT . DIRECTORY_SEPARATOR . 'config' .
            DIRECTORY_SEPARATOR . 'Cabin'.
            DIRECTORY_SEPARATOR . $cabinName .
            DIRECTORY_SEPARATOR . 'config.json'
        );
        $gadgets = ROOT . DIRECTORY_SEPARATOR . 'config' .
            DIRECTORY_SEPARATOR . 'Cabin'.
            DIRECTORY_SEPARATOR . $cabinName .
            DIRECTORY_SEPARATOR . 'gadgets.json';
        if (!\file_exists($gadgets)) {
            \file_put_contents($gadgets, '[]');
        }
        $settings['gadgets'] = \Airship\loadJSON($gadgets);
        $settings['motifs'] = $this->getCabinsMotifs($cabinName);
        $settings['twig_vars'] = \Airship\loadJSON(
            ROOT . DIRECTORY_SEPARATOR . 'config' .
            DIRECTORY_SEPARATOR . 'Cabin'.
            DIRECTORY_SEPARATOR . $cabinName .
            DIRECTORY_SEPARATOR . 'twig_vars.json'
        );

        $this->lens('cabin_manage', [
            'name' => $cabinName,
            'config' => $settings
        ]);
    }

    /**
     * Ensure that a cabin link exists
     *
     * @param string $cabin
     * @return bool
     */
    protected function ensureCabinLinkExists(string $cabin): bool
    {
        if (!\is_dir(ROOT . '/Cabin/Bridge/Lens/cabin_links/')) {
            \mkdir(ROOT . '/Cabin/Bridge/Lens/cabin_links/', 0775);
        }
        if (!\is_link(ROOT . '/Cabin/Bridge/Lens/cabin_links/' . $cabin)) {
            if (\file_exists(ROOT . '/Cabin/Bridge/Lens/cabin_links/' . $cabin)) {
                \unlink(ROOT . '/Cabin/Bridge/Lens/cabin_links/' . $cabin);
            }
            return \symlink(
                ROOT . '/Cabin/' . $cabin . '/config/editor_templates',
                ROOT . '/Cabin/Bridge/Lens/cabin_links/' . $cabin
            );
        }
        return true;
    }

    /**
     * Load a JSON configuration file
     *
     * @param string $cabin
     * @param string $name
     * @param string $ds
     * @return array
     */
    protected function loadJSONConfigFile(
        string $cabin,
        string $name,
        string $ds = DIRECTORY_SEPARATOR
    ): array {
        try {
            return \Airship\loadJSON(
                ROOT .
                $ds . 'config' .
                $ds . 'Cabin' .
                $ds . $cabin .
                $ds . $name
            );
        } catch (FileNotFound $ex) {
            return [];
        }
        // Let all other errors throw
    }

    /**
     * Attempt to save the user-provided cabin configuration
     *
     * @param string $cabinName
     * @param array $cabins
     * @param array $post
     * @return bool
     */
    protected function saveSettings(
        string $cabinName,
        array $cabins = [],
        array $post = []
    ): bool {
        $ds = DIRECTORY_SEPARATOR;
        $twigEnv = \Airship\configWriter(ROOT . $ds . 'config' . $ds . 'templates');

        // Content-Security-Policy
        $csp = [];
        foreach ($post['content_security_policy'] as $dir => $rules) {
            if ($dir === 'upgrade-insecure-requests' || $dir === 'inherit') {
                continue;
            }
            if (empty($rules['allow'])) {
                $csp[$dir]['allow'] = [];
            } else {
                $csp[$dir]['allow'] = [];
                foreach ($rules['allow'] as $url) {
                    if (!empty($url) && \is_string($url)) {
                        $csp[$dir]['allow'][] = $url;
                    }
                }
            }
            if (isset($rules['disable-security'])) {
                $csp[$dir]['allow'] []= '*';
            }
            if ($dir === 'script-src') {
                $csp[$dir]['unsafe-inline'] = !empty($rules['unsafe-inline']);
                $csp[$dir]['unsafe-eval'] = !empty($rules['unsafe-eval']);
                $csp[$dir]['self'] = !empty($rules['self']);
            } elseif ($dir === 'style-src') {
                $csp[$dir]['unsafe-inline'] = !empty($rules['unsafe-inline']);
                $csp[$dir]['self'] = !empty($rules['self']);
            } elseif ($dir !== 'plugin-types') {
                $csp[$dir]['self'] = !empty($rules['self']);
                $csp[$dir]['data'] = !empty($rules['data']);
            }
        }
        $csp['inherit'] = !empty($post['content_security_policy']['inherit']);
        $csp['upgrade-insecure-requests'] = !empty($post['content_security_policy']['upgrade-insecure-requests']);

        $saveCabins = [];
        foreach ($cabins as $cab => $cab_data) {
            if ($cab_data['name'] !== $cabinName) {
                // Pass-through
                $saveCabins[$cab] = $cab_data;
            } else {
                $saveCabins[$post['config']['path']] = $post['config'];
                if (isset($cab_data['namespace'])) {
                    // This should be immutable.
                    $saveCabins[$post['config']['path']]['namespace'] = $cab_data['namespace'];
                }
                unset($saveCabins['path']);
            }
        }

        // Save CSP
        \file_put_contents(
            ROOT . $ds . 'config' . $ds . 'Cabin' . $ds . $cabinName . $ds . 'content_security_policy.json',
            \json_encode($csp, JSON_PRETTY_PRINT)
        );

        // Configuration
        if (!empty($post['cabin_manage_fallback'])) {
            $config_extra = \json_decode($post['config_extra'], true);
            $twig_vars = \json_decode($post['twig_vars'], true);
        } else {
            $config_extra = $post['config_extra'];
            $twig_vars = $post['twig_vars'];
        }
        if (!empty($config_extra)) {
            \Airship\saveJSON(
                ROOT . DIRECTORY_SEPARATOR . 'config' .
                DIRECTORY_SEPARATOR . 'Cabin'.
                DIRECTORY_SEPARATOR . $cabinName .
                DIRECTORY_SEPARATOR . 'config.json',
                $config_extra
            );
        }

        if (!empty($twig_vars)) {
            \Airship\saveJSON(
                ROOT . DIRECTORY_SEPARATOR . 'config' .
                    DIRECTORY_SEPARATOR . 'Cabin'.
                    DIRECTORY_SEPARATOR . $cabinName .
                    DIRECTORY_SEPARATOR . 'twig_vars.json',
                $twig_vars
            );
        }

        // Clear the cache
        \unlink(ROOT . DIRECTORY_SEPARATOR .
            'tmp' . DIRECTORY_SEPARATOR .
            'cache' . DIRECTORY_SEPARATOR .
            'csp.' . $cabinName . '.json'
        );

        if (\extension_loaded('apcu')) {
            \apcu_clear_cache();
        }

        // Save cabins.json
        \file_put_contents(
            ROOT . $ds . 'config' . $ds . 'cabins.json',
            $twigEnv->render('cabins.twig', ['cabins' => $saveCabins])
        );

        // Delete the cabin cache
        if (\file_exists(ROOT . 'tmp' . $ds . 'cache' . $ds . 'cabin_data.json')) {
            \unlink(ROOT . 'tmp' . $ds . 'cache' . $ds . 'cabin_data.json');
        }
        return true;
    }

    /**
     * Get all of the motifs that belong to a particular cabin
     *
     * @param string $cabin
     * @return array
     */
    protected function getCabinsMotifs(string $cabin): array
    {
        $config = \Airship\loadJSON(
            ROOT . DIRECTORY_SEPARATOR . 'config' .
            DIRECTORY_SEPARATOR . 'Cabin'.
            DIRECTORY_SEPARATOR . $cabin .
            DIRECTORY_SEPARATOR . 'motifs.json'
        );
        $motifs = [];
        foreach ($config as $name => $conf) {
            $json = \Airship\loadJSON(ROOT . '/Motifs/' . $conf['path'] . '/motif.json');
            $motifs[$name] = [
                'path' => $conf['path'],
                'config' => $json
            ];
        }
        return $motifs;
    }
}