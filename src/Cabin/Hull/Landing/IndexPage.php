<?php
declare(strict_types=1);
namespace Airship\Cabin\Hull\Landing;

require_once __DIR__.'/gear.php';

class IndexPage extends LandingGear
{   
    public function index()
    {
        $this->blog = $this->blueprint('Blog');

        $blogroll = $this->blog->recentFullPosts(5);
        $mathjax = false;
        foreach ($blogroll as $i => $blog) {
            $blogroll[$i] = $this->blog->getSnippet($blog);
            if (\strlen($blogroll[$i]['snippet']) !== \strlen($blog['body'])) {
                $blogroll[$i]['snippet'] = \rtrim($blogroll[$i]['snippet'], "\n");
            }
            $mathjax |= \strpos($blog['body'], '$$') !== false;
        }

        $this->resetBaseTemplate();
        $this->lens('index', [
            'blogposts' => $blogroll
        ]);
    }
}
