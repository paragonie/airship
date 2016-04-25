<?php
declare(strict_types=1);
namespace Airship\Cabin\Hull\Landing;

use Airship\Cabin\Hull\Blueprint\Blog;

require_once __DIR__.'/gear.php';

class IndexPage extends LandingGear
{
    protected $blog;

    /**
     * The homepage for an Airship.
     *
     * @route /
     */
    public function index()
    {
        $this->blog = $this->blueprint('Blog');
        if (IDE_HACKS) {
            $this->blog = new Blog(\Airship\get_database());
        }

        $blogRoll = $this->blog->recentFullPosts(5);
        $mathJAX = false;
        foreach ($blogRoll as $i => $blog) {
            $blogRoll[$i] = $this->blog->getSnippet($blog);
            if (\strlen($blogRoll[$i]['snippet']) !== \strlen($blog['body'])) {
                $blogRoll[$i]['snippet'] = \rtrim($blogRoll[$i]['snippet'], "\n");
            }
            $mathJAX |= \strpos($blog['body'], '$$') !== false;
        }

        $this->lens('index', [
            'blogposts' => $blogRoll
        ]);
    }
}
