<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Landing;

use \Airship\Cabin\Bridge\Blueprint\UserAccounts;
use \Airship\Alerts\FileSystem\FileNotFound;
use \Airship\Engine\State;

require_once __DIR__.'/init_gear.php';

/**
 * Class Admin
 * @package Airship\Cabin\Bridge\Landing
 */
class Admin extends AdminOnly
{
    /**
     * @var UserAccounts
     */
    private $acct;

    public function airshipLand()
    {
        parent::airshipLand();
        $this->acct = $this->blueprint('UserAccounts');
    }

    /**
     * @route admin
     */
    public function index()
    {
        $this->lens('admin');
    }

    /**
     * @route admin/databases
     */
    public function manageDatabase()
    {
        $this->lens('admin_databases');
    }

    /**
     * Landing page for the Administrative > Extensions section
     *
     * @route admin/extensions
     */
    public function manageExtensions()
    {
        $this->lens('admin_extensions');
    }

    /**
     * Landing page for the Administrative > Extensions section
     *
     * @route admin/notaries
     */
    public function manageNotaries()
    {
        $channels = \Airship\loadJSON(ROOT . '/config/channels.json');
        foreach ($channels as $chanName => $chanConfig) {
            $channels[$chanName]['notaries'] = \Airship\loadJSON(
                ROOT . '/config/channel_peers/' . $chanName . '.json'
            );
        }

        $post = $this->post();
        if (!empty($post)) {

        }
        $this->lens('admin_notaries', [
            'channels' => $channels
        ]);
    }

    /**
     * @route admin/settings
     */
    public function manageSettings()
    {
        $state = State::instance();
        $settings = [
            'universal' => $state->universal
        ];

        $post = $this->post();
        if (!empty($post)) {
            if ($this->saveSettings($post)) {
                $this->storeLensVar('post_response', [
                    'status' => 'OK',
                    'message' => \__(
                        'Your changes have been made successfully.'
                    )
                ]);
            }
        }

        // Load individual files...
        $settings['cabins'] =
            $this->loadJSONConfigFile('cabins.json');
        $settings['content_security_policy'] =
            $this->loadJSONConfigFile('content_security_policy.json');
        $settings['gears'] =
            $this->loadJSONConfigFile('gears.json');
        $settings['keyring'] =
            $this->loadJSONConfigFile('keyring.json');

        foreach (\Airship\list_all_files(ROOT . '/config/supplier_keys/', 'json') as $supplier) {
            $name = \Airship\path_to_filename($supplier, true);
            $settings['suppliers'][$name] = \Airship\loadJSON($supplier);
        }

        $this->lens('admin_settings', [
            'config' => $settings,
            'groups' => $this->acct->getGroupTree()
        ]);
    }

    /**
     * Load a JSON configuration file
     *
     * @param string $name
     * @param string $ds
     * @return array
     */
    protected function loadJSONConfigFile(
        string $name,
        string $ds = DIRECTORY_SEPARATOR
    ): array {
        try {
            return \Airship\loadJSON(ROOT . $ds . 'config' . $ds . $name);
        } catch (FileNotFound $ex) {
            return [];
        }
        // Let all other errors throw
    }

    /**
     * Save universal settings
     *
     * @param array $post
     * @return bool
     */
    protected function saveSettings(array $post = []): bool
    {
        $ds = DIRECTORY_SEPARATOR;
        $twigEnv = \Airship\configWriter(ROOT . $ds . 'config' . $ds . 'templates');
        $csp = [];
        foreach ($post['content_security_policy'] as $dir => $rules) {
            if ($dir === 'upgrade-insecure-requests') {
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
        $csp['upgrade-insecure-requests'] = !empty($post['content_security_policy']['upgrade-insecure-requests']);
        if (isset($csp['inherit'])) {
            unset($csp['inherit']);
        }

        // Save CSP
        \file_put_contents(
            ROOT . $ds . 'config' . $ds . 'content_security_policy.json',
            \json_encode($csp, JSON_PRETTY_PRINT)
        );
        if (empty($post['universal']['guest_groups'])) {
            $post['universal']['guest_groups'] = [];
        } else {
            foreach ($post['universal']['guest_groups'] as $i => $g) {
                $post['universal']['guest_groups'][$i] = (int)$g;
            }
        }

        // Save universal config
        \file_put_contents(
            ROOT . $ds . 'config' . $ds . 'universal.json',
            $twigEnv->render('universal.twig', ['universal' => $post['universal']])
        );
        return true;
    }
}
