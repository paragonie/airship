<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Landing;

use \Airship\Alerts\FileSystem\FileNotFound;
use \Airship\Cabin\Bridge\Blueprint\UserAccounts;
use \Airship\Engine\{
    Hail,
    Security\Util,
    State
};
use \Airship\Engine\Security\Filter\{
    ArrayFilter,
    BoolFilter,
    FloatFilter,
    IntFilter,
    GeneralFilterContainer,
    StringFilter
};
use \GuzzleHttp\Client;

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

        $post = $this->post($this->getNotaryFilterContainer());
        if (!empty($post)) {
            if (!empty($post['new_notary_submit'])) {
                $this->addNotary($channels, $post);
            } elseif (!empty($post['delete_notary'])) {
                $this->deleteNotary($channels, $post);
            }
            foreach ($channels as $chanName => $chanConfig) {
                $channels[$chanName]['notaries'] = \Airship\loadJSON(
                    ROOT . '/config/channel_peers/' . $chanName . '.json'
                );
            }
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

        $post = $this->post($this->getSettingsFilterContainer());
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
        $settings['keyring'] =
            $this->loadJSONConfigFile('keyring.json');

        foreach (\Airship\list_all_files(ROOT . '/config/supplier_keys/', 'json') as $supplier) {
            $name = \Airship\path_to_filename($supplier, true);
            $settings['suppliers'][$name] = \Airship\loadJSON($supplier);
        }

        $this->lens(
            'admin_settings',
            [
                'config' => $settings,
                'groups' => $this->acct->getGroupTree()
            ]
        );
    }

    /**
     * @return GeneralFilterContainer
     */
    protected function getNotaryFilterContainer(): GeneralFilterContainer
    {
        return (new GeneralFilterContainer())
            ->addFilter('delete_notary', new StringFilter())
            ->addFilter('new_notary', new StringFilter());
    }

    /**
     * @return GeneralFilterContainer
     */
    protected function getSettingsFilterContainer(): GeneralFilterContainer
    {
        return (new GeneralFilterContainer())
        // content_security_policy.json
            ->addFilter('content_security_policy.connect-src.allow', new ArrayFilter())
            ->addFilter('content_security_policy.connect-src.data', new BoolFilter())
            ->addFilter('content_security_policy.connect-src.self', new BoolFilter())
            ->addFilter('content_security_policy.child-src.allow', new ArrayFilter())
            ->addFilter('content_security_policy.child-src.data', new BoolFilter())
            ->addFilter('content_security_policy.child-src.self', new BoolFilter())
            ->addFilter('content_security_policy.form-action.allow', new ArrayFilter())
            ->addFilter('content_security_policy.form-action.self', new BoolFilter())
            ->addFilter('content_security_policy.font-src.allow', new ArrayFilter())
            ->addFilter('content_security_policy.font-src.data', new BoolFilter())
            ->addFilter('content_security_policy.font-src.self', new BoolFilter())
            ->addFilter('content_security_policy.frame-ancestors.allow', new ArrayFilter())
            ->addFilter('content_security_policy.frame-ancestors.self', new BoolFilter())
            ->addFilter('content_security_policy.img-src.allow', new ArrayFilter())
            ->addFilter('content_security_policy.img-src.data', new BoolFilter())
            ->addFilter('content_security_policy.img-src.self', new BoolFilter())
            ->addFilter('content_security_policy.media-src.allow', new ArrayFilter())
            ->addFilter('content_security_policy.media-src.self', new BoolFilter())
            ->addFilter('content_security_policy.object-src.allow', new ArrayFilter())
            ->addFilter('content_security_policy.object-src.data', new BoolFilter())
            ->addFilter('content_security_policy.object-src.self', new BoolFilter())
            ->addFilter('content_security_policy.script-src.allow', new ArrayFilter())
            ->addFilter('content_security_policy.script-src.data', new BoolFilter())
            ->addFilter('content_security_policy.script-src.self', new BoolFilter())
            ->addFilter('content_security_policy.script-src.unsafe-eval', new BoolFilter())
            ->addFilter('content_security_policy.script-src.unsafe-inline', new BoolFilter())
            ->addFilter('content_security_policy.style-src.allow', new ArrayFilter())
            ->addFilter('content_security_policy.style-src.data', new BoolFilter())
            ->addFilter('content_security_policy.style-src.self', new BoolFilter())
            ->addFilter('content_security_policy.style-src.unsafe-inline', new BoolFilter())
            ->addFilter('content_security_policy.upgrade-insecure-requests', new BoolFilter())
        // universal.json
            ->addFilter(
                'universal.auto-update.check',
                (new IntFilter())
                    ->setDefault(3600)
            )
            ->addFilter('universal.auto-update.enabled', new BoolFilter())
            ->addFilter('universal.debug', new BoolFilter())
            ->addFilter('universal.email.from', new StringFilter())
            ->addFilter(
                'universal.ledger.driver',
                (new StringFilter())->addCallback(function ($driver): string {
                    if ($driver === 'file') {
                        return 'file';
                    } elseif ($driver === 'database') {
                        return 'database';
                    }
                    throw new \TypeError(
                        'Invalid Ledger driver'
                    );
                })
            )
            ->addFilter('universal.guest_groups', new ArrayFilter())
            ->addFilter('universal.ledger.path', new StringFilter())
            ->addFilter(
                'universal.notary.channel',
                (new StringFilter())
                    ->addCallback(function () {
                        // In the future, this will be changeable.
                        return 'paragonie';
                    })
            )
            ->addFilter('universal.notary.enabled', new BoolFilter())
            ->addFilter('universal.rate-limiting.expire', new IntFilter())
            ->addFilter('universal.rate-limiting.fast-exit', new BoolFilter())
            ->addFilter('universal.rate-limiting.first-delay', new FloatFilter())
            ->addFilter(
                'universal.rate-limiting.ipv4-subnet',
                (new IntFilter())->setDefault(32)
            )
            ->addFilter(
                'universal.rate-limiting.ipv6-subnet',
                (new IntFilter())->setDefault(64)
            )
            ->addFilter('universal.rate-limiting.log-after', new IntFilter())
            ->addFilter(
                'universal.rate-limiting.max-delay',
                (new IntFilter())->setDefault(30)
            )
            ->addFilter(
                'universal.rate-limiting.log-public-key',
                (new StringFilter())->addCallback(function ($str): string {
                    if (empty($str)) {
                        return '';
                    }
                    if (\preg_match('#^[0-9A-Fa-f]{64}$#', $str)) {
                        return $str;
                    }
                    return '';
                })
            )
            ->addFilter(
                'universal.session_index.logout_token',
                (new StringFilter())->setDefault('logout_token')
            )
            ->addFilter(
                'universal.session_index.user_id',
                (new StringFilter())->setDefault('userid')
            )
            ->addFilter('universal.session_config.cookie_domain', new StringFilter())
            ->addFilter('universal.tor-only', new BoolFilter())
            ->addFilter('universal.twig-cache', new BoolFilter())
            ->addFilter('universal.trusted-supplier', new StringFilter())
        ;
    }

    /**
     * Discover a new notary, grab its public key, channels, and URL
     *
     * @param string $url
     * @return array
     */
    protected function notaryDiscovery(string $url): array
    {
        $state = State::instance();
        if (IDE_HACKS) {
            $state->hail = new Hail(new Client());
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
        \Airship\saveJSON(
            ROOT . $ds . 'config' . $ds . 'content_security_policy.json',
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
        \file_put_contents(
            ROOT . $ds . 'config' . $ds . 'universal.json',
            $twigEnv->render('universal.twig', ['universal' => $post['universal']])
        );
        return true;
    }
}
