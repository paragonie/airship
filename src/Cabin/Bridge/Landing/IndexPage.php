<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Landing;

use Airship\Cabin\Bridge\Blueprint\Author;
use Airship\Cabin\Bridge\Blueprint\Blog;
use Airship\Cabin\Bridge\Blueprint\CustomPages;
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
     * @route /
     */
    public function index()
    {
        if ($this->isLoggedIn())  {
            $author_bp = $this->blueprint('Author');
            $blog_bp = $this->blueprint('Blog');
            # $user_bp = $this->blueprint('UserAccounts');
            $page_bp = $this->blueprint('CustomPages');
            if (IDE_HACKS) {
                $db = \Airship\get_database();
                $author_bp = new Author($db);
                $blog_bp = new Blog($db);
                $page_bp = new CustomPages($db);
            }

            $this->lens('index', [
                'stats' => [
                    'num_authors' =>
                        $author_bp->numAuthors(),
                    'num_comments' =>
                        $blog_bp->numComments(true),
                    'num_pages' =>
                        $page_bp->numCustomPages(true),
                    'num_posts' =>
                        $blog_bp->numPosts(true)
                ]
            ]);
        } else {
            $this->lens('login');
        }
    }

    /**
     * @route help
     */
    public function helpPage()
    {
        if ($this->isLoggedIn())  {
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
                    'cookie_index',
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
                    'airship' => \AIRSHIP_VERSION,
                    'helpInfo' => $helpInfo
                ]
            );
        } else {
            // Not a registered user? Go read the docs. No info leaks for you!
            \Airship\redirect('https://github.com/paragonie/airship-docs');
        }
    }
}
