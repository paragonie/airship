<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Landing;

use Airship\Cabin\Bridge\Blueprint\{
    Announcements,
    Author,
    Blog,
    CustomPages
};
use Airship\Cabin\Bridge\Filter\AnnounceFilter;
use Airship\Engine\State;
use ParagonIE\Halite\Halite;

require_once __DIR__.'/init_gear.php';

/**
 * Class IndexPage
 * @package Airship\Cabin\Bridge\Landing
 */
class IndexPage extends LandingGear
{
    /**
     * @route announce
     */
    public function announce()
    {
        if (!$this->isLoggedIn())  {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        $this->storeLensVar('showmenu', true);
        if (!$this->can('create')) {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        $announce_bp = $this->blueprint('Announcements');
        if (IDE_HACKS) {
            $announce_bp = new Announcements();
        }

        $post = $this->post(new AnnounceFilter());
        if ($post) {
            if ($announce_bp->createAnnouncement($post)) {
                \Airship\redirect(
                    $this->airship_cabin_prefix
                );
            }
        }
        $this->lens(
            'announce',
            [
                'active_link' =>
                    'bridge-link-announce',
                'title' =>
                    \__('New Announcement')
            ]
        );
    }

    /**
     * @route /
     */
    public function index()
    {
        if ($this->isLoggedIn())  {
            $this->storeLensVar('showmenu', true);
            $author_bp = $this->blueprint('Author');
            $announce_bp = $this->blueprint('Announcements');
            $blog_bp = $this->blueprint('Blog');
            $page_bp = $this->blueprint('CustomPages');
            if (IDE_HACKS) {
                $db = \Airship\get_database();
                $author_bp = new Author($db);
                $announce_bp = new Announcements($db);
                $blog_bp = new Blog($db);
                $page_bp = new CustomPages($db);
            }

            $this->lens('index',
                [
                    'announcements' =>
                        $announce_bp->getForUser(
                            $this->getActiveUserId()
                        ),
                    'stats' => [
                        'num_authors' =>
                            $author_bp->numAuthors(),
                        'num_comments' =>
                            $blog_bp->numComments(true),
                        'num_pages' =>
                            $page_bp->numCustomPages(true),
                        'num_posts' =>
                            $blog_bp->numPosts(true)
                    ],
                    'title' => \__('Dashboard')
                ]
            );
        } else {
            $this->storeLensVar('showmenu', false);
            $this->lens('login');
        }
    }

    /**
     * @route error
     */
    public function error()
    {
        if (empty($_GET['error'])) {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        if ($_GET['error'] === '403 Forbidden') {
            \http_response_code(403);
        }
        switch ($_GET['error']) {
            case '403 Forbidden':
                $this->lens(
                    'error',
                    [
                        'error' =>
                            \__($_GET['error'])
                    ]
                );
                break;
            default:
                \Airship\redirect($this->airship_cabin_prefix);
        }
    }

    /**
     * @route help
     */
    public function helpPage()
    {
        if ($this->isLoggedIn())  {
            $this->storeLensVar('showmenu', true);
            //
            $cabins = $this->getCabinNamespaces();

            // Get debug information.
            $helpInfo = [
                'cabins' => [],
                'cabin_names' => \array_values($cabins),
                'gears' => [],
                'universal' => []
            ];

            /**
             * This might reveal "sensitive" information. By default, it's
             * locked out of non-administrator users. You can grant access to
             * other users/groups via the Permissions menu.
             */
            if ($this->can('read')) {
                $state = State::instance();
                if (\is_readable(ROOT . '/config/gadgets.json')) {
                    $helpInfo['universal']['gadgets'] = \Airship\loadJSON(
                        ROOT . '/config/gadgets.json'
                    );
                }
                if (\is_readable(ROOT . '/config/content_security_policy.json')) {
                    $helpInfo['universal']['content_security_policy'] = \Airship\loadJSON(
                        ROOT . '/config/content_security_policy.json'
                    );
                }
                foreach ($cabins as $cabin) {
                    $cabinData = [
                        'config' => \Airship\loadJSON(
                            ROOT . '/Cabin/' . $cabin . '/manifest.json'
                        ),
                        'content_security_policy' => [],
                        'gadgets' => [],
                        'motifs' => [],
                        'user_motifs' => \Airship\LensFunctions\user_motif(
                            $this->getActiveUserId(),
                            $cabin
                        )
                    ];

                    $prefix  = ROOT . '/Cabin/' . $cabin . '/config/';
                    if (\is_readable($prefix . 'gadgets.json')) {
                        $cabinData['gadgets'] = \Airship\loadJSON(
                            $prefix . 'gadgets.json'
                        );
                    }
                    if (\is_readable($prefix . 'motifs.json')) {
                        $cabinData['motifs'] = \Airship\loadJSON(
                            $prefix . 'motifs.json'
                        );
                    }
                    if (\is_readable($prefix . 'content_security_policy.json')) {
                        $cabinData['content_security_policy'] = \Airship\loadJSON(
                            $prefix . 'content_security_policy.json'
                        );
                    }

                    $helpInfo['cabins'][$cabin] = $cabinData;
                }
                $helpInfo['gears'] = [];
                foreach ($state->gears as $gear => $latestGear) {
                    $helpInfo['gears'][$gear] = \Airship\get_ancestors($latestGear);
                }

                // Only grab data likely to be pertinent to common issues:
                $keys = [
                    'airship',
                    'auto-update',
                    'debug',
                    'guzzle',
                    'notary',
                    'rate-limiting',
                    'session_config',
                    'tor-only',
                    'twig_cache'
                ];
                $helpInfo['universal']['config'] = \Airship\keySlice(
                    $state->universal,
                    $keys
                );

                $helpInfo['php'] = [
                    'halite' =>
                        Halite::VERSION,
                    'libsodium' => [
                        'major' =>
                            \Sodium\library_version_major(),
                        'minor' =>
                            \Sodium\library_version_minor(),
                        'version' =>
                            \Sodium\version_string()
                    ],
                    'version' =>
                        \PHP_VERSION,
                    'versionid' =>
                        \PHP_VERSION_ID
                ];
            }

            $this->lens(
                'help',
                [
                    'active_link' => 'bridge-link-help',
                    'airship' => \AIRSHIP_VERSION,
                    'helpInfo' => $helpInfo
                ]
            );
        } else {
            // Not a registered user? Go read the docs. No info leaks for you!
            \Airship\redirect('https://github.com/paragonie/airship-docs');
        }
    }

    /**
     *
     * @route motif_extra.css
     */
    public function motifExtra()
    {
        $this->lens('motif_extra', [], 'text/css; charset=UTF-8');
    }
}
