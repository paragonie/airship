<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Landing;

use \Airship\Alerts\FileSystem\FileNotFound;
use \Airship\Engine\State;

require_once __DIR__.'/gear.php';

class Cabins extends LoggedInUsersOnly
{
    /**
     * @route cabins
     */
    public function index()
    {
        if (!$this->isLoggedIn()) {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        $this->lens('cabins', [
            'cabins' => $this->getCabinNames()
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
        if (!\in_array($cabinName, $this->getCabinNames())) {
            // Invalid cabin name
            \Airship\redirect($this->airship_cabin_prefix . '/cabins');
        }
        $cabin = \Airship\loadJSON(
            ROOT . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'cabins.json'
        );
        $post = $this->post();
        if (!empty($post)) {
            if ($this->saveSettings($cabinName, $cabin, $post)) {
                $this->storeLensVar('post_response', [
                    'status' => 'OK',
                    'message' => \__(
                        'Your changes have been made successfully.'
                    )
                ]);

                // Reload configuration for page render
                $cabin = \Airship\loadJSON(
                    ROOT . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'cabins.json'
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
        /*
        @todo Implement these before second beta

        $settings['gadgets'] = \Airship\loadJSON(
            ROOT . DIRECTORY_SEPARATOR . 'config' .
            DIRECTORY_SEPARATOR . 'Cabin'.
            DIRECTORY_SEPARATOR . $cabinName .
            DIRECTORY_SEPARATOR . 'gadgets.json'
        );
        $settings['motifs'] = \Airship\loadJSON(
            ROOT . DIRECTORY_SEPARATOR . 'config' .
            DIRECTORY_SEPARATOR . 'Cabin'.
            DIRECTORY_SEPARATOR . $cabinName .
            DIRECTORY_SEPARATOR . 'motifs.json'
        );
        */
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
            } elseif ($dir === 'style-src') {
                $csp[$dir]['unsafe-inline'] = !empty($rules['unsafe-inline']);
            } elseif ($dir !== 'plugin-types') {
                $csp[$dir]['self'] = !empty($rules['self']);
                $csp[$dir]['data'] = !empty($rules['data']);
            }
        }
        $csp['inherit'] = !empty($post['content-security-policy']['inherit']);
        $csp['upgrade-insecure-requests'] = !empty($post['content_security_policy']['upgrade-insecure-requests']);

        $saveCabins = [];
        foreach ($cabins as $cab => $cab_data) {
            if ($cab_data['name'] !== $cabinName) {
                // Pass-through
                $saveCabins[$cab] = $cab_data;
            } else {
                $saveCabins[$post['config']['path']] = $post['config'];
                unset($saveCabins['path']);
            }
        }
        // Save CSP
        \file_put_contents(
            ROOT . $ds . 'config' . $ds . $cabinName . $ds . 'content_security_policy.json',
            \json_encode($csp, JSON_PRETTY_PRINT)
        );

        // Configuration
        $config_extra = \json_decode($post['config_extra'], true);
        if (!empty($config_extra)) {
            \file_put_contents(
                ROOT . DIRECTORY_SEPARATOR . 'config' .
                DIRECTORY_SEPARATOR . 'Cabin'.
                DIRECTORY_SEPARATOR . $cabinName .
                DIRECTORY_SEPARATOR . 'config.json',
                \json_encode($config_extra, JSON_PRETTY_PRINT)
            );
        }

        $twig_vars = \json_decode($post['twig_vars'], true);
        if (!empty($twig_vars)) {
            \file_put_contents(
                ROOT . DIRECTORY_SEPARATOR . 'config' .
                    DIRECTORY_SEPARATOR . 'Cabin'.
                    DIRECTORY_SEPARATOR . $cabinName .
                    DIRECTORY_SEPARATOR . 'twig_vars.json',
                \json_encode($twig_vars, JSON_PRETTY_PRINT)
            );
        }

        // Clear the cache
        \unlink(ROOT . DIRECTORY_SEPARATOR .
            'tmp' . DIRECTORY_SEPARATOR .
            'cache' . DIRECTORY_SEPARATOR .
            'csp.' . $cabinName . '.json'
        );

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
}