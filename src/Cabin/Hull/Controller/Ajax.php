<?php
declare(strict_types=1);
namespace Airship\Cabin\Hull\Controller;

use Airship\Cabin\Hull\Model\Blog;
use Airship\Cabin\Hull\Filter\Ajax\CommentForm;
use Airship\Engine\Contract\CacheInterface;
use Airship\Engine\Model;

require_once __DIR__.'/init_gear.php';

/**
 * Class Ajax
 *
 * Manage the front-facing AJAX responders
 *
 * @package Airship\Cabin\Hull\Controller
 */
class Ajax extends ControllerGear
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
        $blog = $this->model('Blog');
        if (!$blog instanceof Blog) {
            throw new \TypeError(Model::TYPE_ERROR);
        }
        $this->blog = $blog;
    }

    /**
     * Just get the blog comment reply form on cached pages
     *
     * @route ajax/blog_comment_form
     * @return void
     */
    public function blogCommentForm(): void
    {
        $filter = new CommentForm();
        try {
            $post = $filter($_POST);
        } catch (\TypeError $ex) {
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => 'Invalid POST data'
            ]);
            return;
        }

        $formAction = '/' .
            \trim(
                \implode(
                    '/',
                    [
                        $this->airship_cabin_prefix,
                        'blog',
                        $post['year'],
                        $post['month'],
                        $post['slug']
                    ]
                ),
                '/'
            );

        $this->view(
            'blog/comment_form',
            [
                'form_action' => $formAction,
                'config' => $this->config()
            ]
        );
    }

    /**
     * @route ajax/clear_cache{_string}
     * @param string $key
     * @return void
     */
    public function clearCache(string $key): void
    {
        $secret = $this->config('cache-secret');
        if ($secret === null) {
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => 'Cache-clearing secret value not set'
            ]);
        }
        if (!\hash_equals($secret, $key)) {
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
     * @return void
     */
    public function loadComments(): void
    {
        $cache = $this->blog->getCommentCache();
        $blog = (string) ($_POST['blogpost'] ?? '');
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
     * @return void
     */
    protected function fetchComments(CacheInterface $cache, string $uniqueID): void
    {
        $blog = $this->blog->getBlogPostByUniqueId($uniqueID);
        $comments = $this->blog->getCommentTree((int) $blog['postid']);
        $contents = $this->viewRender(
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
