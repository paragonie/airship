<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Landing;

use Airship\Cabin\Bridge\Blueprint\Author;
use Airship\Cabin\Bridge\Blueprint\Blog;
use Airship\Cabin\Bridge\Blueprint\CustomPages;

require_once __DIR__.'/gear.php';

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
}
