<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Landing;

use Airship\Alerts\FileSystem\FileNotFound;
use Airship\Cabin\Bridge\Blueprint\UserAccounts;
use Airship\Cabin\Bridge\Filter\Admin\{
    DatabaseFilter,
    NotaryFilter,
    SettingsFilter
};
use Airship\Engine\{
    Hail,
    Security\Util,
    State
};
use Psr\Log\LogLevel;

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
    protected $acct;

    /**
     * This function is called after the dependencies have been injected by
     * AutoPilot. Think of it as a user-land constructor.
     */
    public function airshipLand()
    {
        parent::airshipLand();
        $this->acct = $this->blueprint('UserAccounts');
        if (!empty($_GET['msg'])) {
            if ($_GET['msg'] === 'saved') {
                $this->storeLensVar(
                    'post_response',
                    [
                        'status' => 'OK',
                        'message' => \__(
                            'Your changes have been made successfully.'
                        )
                    ]
                );
            }
        }
        $this->storeLensVar('active_submenu', 'Admin');
    }

    /**
     * Add a new notary
     *
     * @param array $channels
     * @param array $post
     * @return bool
     */
    protected function addNotary(array $channels, array $post): bool
    {
        $ch = $post['channel'];
        if (!isset($channels[$ch])) {
            return false;
        }
        $url = \trim($post['new_notary']);
        $notaryInfo = $this->notaryDiscovery($url);
        if (empty($notaryInfo)) {
            return false;
        }

        $found = false;
        foreach ($channels[$ch]['notaries'] as $i => $notary) {
            if (\in_array($url, $notary['urls'])) {
                if ($notary['public_key'] === $notaryInfo['public_key']) {
                    // Duplicate
                    return false;
                }
                $found = true;
                $channels[$ch]['notaries'][$i]['public_key'] = $notaryInfo['public_key'];
                break;
            }
        }
        if (!$found) {
            $channels[$ch]['notaries'][] = [
                'name' => $post['name'],
                'urls' => [
                    $notaryInfo['url']
                ],
                'public_key' => $notaryInfo['public_key']
            ];
        }

        return \Airship\saveJSON(
            ROOT . '/config/channel_peers/' . $ch . '.json',
            $channels[$ch]['notaries']
        );
    }

    /**
     * Add a new notary
     *
     * @param array $channels
     * @param array $post
     * @return bool
     */
    protected function deleteNotary(array $channels, array $post): bool
    {
        if (\strpos($post['delete_notary'], '|') === false) {
            return false;
        }
        list ($ch, $idx) = \explode('|', $post['delete_notary']);
        if (!isset($channels[$ch])) {
            return false;
        }
        $idx += 0;

        if ($channels[$ch]['notaries'][$idx]) {
            unset($channels[$ch]['notaries'][$idx]);
        }
        return \Airship\saveJSON(
            ROOT . '/config/channel_peers/' . $ch . '.json',
            $channels[$ch]['notaries']
        );
    }

    /**
     * @route admin
     */
    public function index()
    {
        $this->lens('admin');
    }

    /**
     * @route admin/database
     */
    public function manageDatabase()
    {
        $databases = \Airship\loadJSON(
            ROOT . '/config/databases.json'
        );
        $post = $this->post();
        if ($post) {
            if ($this->saveDatabase($post, $databases)) {
                \Airship\redirect(
                    $this->airship_cabin_prefix . '/admin/database',
                    [
                        'msg' => 'saved'
                    ]
                );
            }
        }
        $this->lens(
            'admin_databases',
            [
                'active_link' => 'bridge-link-admin-databases',
                'databases' => $databases
            ]
        );
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
        $this->storeLensVar('active_submenu', ['Admin', 'Extensions']);
        $channels = \Airship\loadJSON(ROOT . '/config/channels.json');
        foreach ($channels as $chanName => $chanConfig) {
            $channels[$chanName]['notaries'] = \Airship\loadJSON(
                ROOT . '/config/channel_peers/' . $chanName . '.json'
            );
        }

        $post = $this->post(new NotaryFilter());
        if (!empty($post)) {
            if (!empty($post['new_notary_submit'])) {
                $this->addNotary($channels, $post);
            } elseif (!empty($post['delete_notary'])) {
                $this->deleteNotary($channels, $post);
            }
            \Airship\redirect(
                $this->airship_cabin_prefix . '/admin/notaries'
            );
        }
        $this->lens('admin_notaries', [
            'active_link' => 'bridge-link-admin-ext-notaries',
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

        $post = $this->post(new SettingsFilter());
        if (!empty($post)) {
            if ($this->saveSettings($post)) {
                \Airship\clear_cache();
                \Airship\redirect(
                    $this->airship_cabin_prefix . '/admin/settings',
                    [
                        'msg' => 'saved'
                    ]
                );
            } else {
                $this->log(
                    'Could not save new settings',
                    LogLevel::ALERT
                );
            }
        }

        // Load individual files...
        $settings['cabins'] =
            $this->loadJSONConfigFile('cabins.json');
        $settings['content_security_policy'] =
            $this->loadJSONConfigFile('content_security_policy.json');
        $settings['keyring'] =
            $this->loadJSONConfigFile('keyring.json');

        foreach (\Airship\list_all_files(ROOT . '/config/supplier_keys/', 'json') as $supplier) {
            $name = \Airship\path_to_filename($supplier, true);
            $settings['suppliers'][$name] = \Airship\loadJSON($supplier);
        }

        $this->lens(
            'admin_settings',
            [
                'active_link' => 'bridge-link-admin-settings',
                'config' => $settings,
                'groups' => $this->acct->getGroupTree()
            ]
        );
    }

    /**
     * Discover a new notary, grab its public key, channels, and URL
     *
     * @param string $url
     * @return array
     * @throws \TypeError
     */
    protected function notaryDiscovery(string $url): array
    {
        $state = State::instance();
        if (!($state->hail instanceof Hail)) {
            throw new \TypeError(
                \trk('errors.type.wrong_class', Hail::class)
            );
        }
        $body = $state->hail->getReturnBody($url);
        $pos = \strpos($body, '<meta name="airship-notary" content="');
        if ($pos === false) {
            // Notary not enabled:
            return [];
        }
        $body = Util::subString($body, $pos + 37);
        $end = \strpos($body, '"');
        if (!$end) {
            // Invalid
            return [];
        }
        $tag = \explode('; ', Util::subString($body, 0, $end));
        $channel = null;
        $notary_url = null;

        foreach ($tag as $t) {
            list ($k, $v) = \explode('=', $t);
            if ($k === 'channel') {
                $channel = $v;
            } elseif ($k === 'url') {
                $notary_url = $v;
            }
        }
        return [
            'public_key' => $tag[0],
            'channel' => $channel,
            'url' => $notary_url
        ];
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
     * Write the updated JSON configuration file.
     *
     * @param array $post
     * @param array $old
     * @return bool
     */
    protected function saveDatabase(array $post = [], array $old = []): bool
    {
        $twigEnv = \Airship\configWriter(ROOT. '/config/templates');

        $databases = [];
        $filter = new DatabaseFilter();
        foreach ($post['db_keys'] as $index => $key) {
            foreach (\array_keys($post['database'][$index]) as $i) {
                if (empty($post['database'][$index][$i]['driver'])) {
                    unset($post['database'][$index][$i]);
                    continue;
                }
                $post['database'][$index][$i]['options'] = \json_decode(
                    $post['database'][$index][$i]['options'],
                    true
                );
                if (empty($post['database'][$index][$i]['password'])) {
                    $post['database'][$index][$i]['password'] = (string) (
                        $old[$key][$i]['password'] ?? ''
                    );
                }
            }
            if (!empty($post['database'][$index])) {
                $databases[$key] = $post['database'][$index];
            }
            $filter->addDatabaseFilters(
                $key,
                \count($post['database'][$index])
            );
        }
        $databases = $filter($databases);

        return \file_put_contents(
            ROOT . '/config/databases.json',
            $twigEnv->render(
                'databases.twig',
                [
                    'databases' => $databases
                ]
            )
        ) !== false;
    }

    /**
     * Save universal settings
     *
     * @param array $post
     * @return bool
     */
    protected function saveSettings(array $post = []): bool
    {
        $filterName = '\\Airship\\Cabin\\' . CABIN_NAME . '\\AirshipFilter';
        if (\class_exists($filterName)) {
            $filter = new $filterName;
            $post = $filter($post);
        }

        $twigEnv = \Airship\configWriter(ROOT. '/config/templates');
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

        if ($post['universal']['ledger']['driver'] === 'database') {
            if (empty($post['universal']['ledger']['table'])) {
                // Table name must be provided.
                return false;
            }
        }

        // Save CSP
        \Airship\saveJSON(
            ROOT . '/config/content_security_policy.json',
            $csp
        );
        if (empty($post['universal']['guest_groups'])) {
            $post['universal']['guest_groups'] = [];
        } else {
            foreach ($post['universal']['guest_groups'] as $i => $g) {
                $post['universal']['guest_groups'][$i] = (int) $g;
            }
        }

        // Save universal config
        return \file_put_contents(
            ROOT . '/config/universal.json',
            $twigEnv->render(
                'universal.twig',
                [
                    'universal' => $post['universal']
                ]
            )
        ) !== false;
    }
}
