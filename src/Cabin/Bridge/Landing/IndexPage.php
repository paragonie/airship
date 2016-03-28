<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Landing;

require_once __DIR__.'/gear.php';

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
            $user_bp = $this->blueprint('UserAccounts');
            $page_bp = $this->blueprint('CustomPages');

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
