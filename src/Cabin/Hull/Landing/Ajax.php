<?php
declare(strict_types=1);
namespace Airship\Cabin\Hull\Landing;

use \Airship\Cabin\Hull\Blueprint\Blog;
use Airship\Engine\Continuum\Updaters\Airship;
use \Airship\Engine\Contract\CacheInterface;

require_once __DIR__.'/init_gear.php';

/**
 * Class Ajax
 *
 * Manage the front-facing AJAX responders
 *
 * @package Airship\Cabin\Hull\Landing
 */
class Ajax extends LandingGear
{
    /**
     * @var Blog
     */
    protected $blog;

    /**
     * This function is called after the dependencies have been injected by
     * AutoPilot. Think of it as a user-land constructor.
     */
    public function airshipLand()
    {
        parent::airshipLand();
        $this->blog = $this->blueprint('Blog');
    }

    /**
     * Just get the blog comment reply form on cached pages
     *
     * @route ajax/blog_comment_form
     */
    public function blogCommentForm()
    {
        if (IDE_HACKS) {
            $this->blog = new Blog();
        }
        $this->lens(
            'blog/comment_form',
            [
                'config' => $this->config()
            ]
        );
    }

    /**
     * @route ajax/clear_cache{_string}
     * @param string $key
     */
    public function clearCache(string $key)
    {
        if (!\hash_equals($this->config('cache-secret'), $key)) {
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => 'Invalid cache-clearing secret value'
            ]);
        }
        \Airship\clear_cache();
        \Airship\json_response([
            'status' => 'OK',
            'message' => 'Cache cleared!'
        ]);
    }

    /**
     * @route ajax/blog_load_comments
     */
    public function loadComments()
    {
        if (IDE_HACKS) {
            $this->blog = new Blog();
        }
        $cache = $this->blog->getCommentCache();
        $blog = (string) $_POST['blogpost'] ?? '';
        if (empty($blog)) {
            \Airship\json_response([
                'status' => 'error',
                'message' => 'No blogpost ID provided'
            ]);
        }
        $cachedComments = $cache->get($blog);
        if ($cachedComments) {
            \Airship\json_response($cachedComments);
        }
        $this->fetchComments($cache, $blog);
    }

    /**
     * @param CacheInterface $cache
     * @param string $uniqueID
     */
    protected function fetchComments(CacheInterface $cache, string $uniqueID)
    {
        $blog = $this->blog->getBlogPostByUniqueId($uniqueID);
        $comments = $this->blog->getCommentTree((int) $blog['postid']);
        $contents = $this->lensRender(
            'blog/comments',
            [
                'blogpost' => $blog,
                'comments' => $comments,
                'config' => $this->config()
            ]
        );
        $cache->set($uniqueID, [
            'status' => 'OK',
            'cached' => $contents
        ]);
        \Airship\json_response([
            'status' => 'OK',
            'cached' => $contents
        ]);
    }
}
