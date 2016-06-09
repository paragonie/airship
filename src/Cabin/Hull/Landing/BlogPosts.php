<?php
declare(strict_types=1);
namespace Airship\Cabin\Hull\Landing;

use \Airship\Alerts\Router\EmulatePageNotFound;
use \Airship\Cabin\Hull\Blueprint\Blog;

require_once __DIR__.'/init_gear.php';

/**
 * Class BlogPosts
 *
 * Read-only access to blog posts.
 *
 * @package Airship\Cabin\Hull\Landing
 */
class BlogPosts extends LandingGear
{
    /**
     * @var Blog
     */
    protected $blog;

    /**f
     * This function is called after the dependencies have been injected by
     * AutoPilot. Think of it as a user-land constructor.
     */
    public function airshipLand()
    {
        $this->blog = $this->blueprint('Blog');
        $this->airship_lens_object->store(
            'blogmenu',
            $this->blog->getBlogMenu()
        );
    }

    /**
     * Add a comment to a blog post
     *
     * @param array $post
     * @param int $blogPostId
     * @return bool
     */
    protected function addComment(
        array $post = [],
        int $blogPostId = 0
    ): bool {
        if (!$this->config('blog.comments.enabled')) {
            $this->storeLensVar(
                'blog_error',
                \__('Comments are not enabled on this blog.')
            );
            return false;
        }
        if (!$this->isLoggedIn() && !$this->config('blog.comments.guests')) {
            $this->storeLensVar(
                'blog_error',
                \__('Guest comments are not enabled on this blog.')
            );
            return false;
        }
        if (!$this->isLoggedIn() && (empty($post['name']) || empty($post['email']))) {
            $this->storeLensVar(
                'blog_error',
                \__('Name and email address are required fields.')
            );
            return false;
        }
        if ($this->isLoggedIn() && !$this->isSuperUser()) {
            $allowedAuthors = $this->blog->getAuthorsForUser($this->getActiveUserId());
            if (!\in_array($post['author'], $allowedAuthors)) {
                $this->storeLensVar(
                    'blog_error',
                    \__('You do not have permission to post as this author.')
                );
                return false;
            }
        }
        $msg = \trim($post['message']);
        if (\strlen($msg) < 2) {
            $this->storeLensVar(
                'blog_error',
                \__('The comment you attempted to leave is much too short.')
            );
            return false;
        }

        $published = false;
        $can_comment = false;

        if ($this->can('publish')) {
            // No CAPTCHA necessary
            $published = true;
            $can_comment = true;
        } elseif (isset($post['g-recaptcha-response'])) {
            $rc = \Airship\getReCaptcha(
                $this->config('recaptcha.secret-key'),
                $this->config('recaptcha.curl-opts') ?? []
            );
            $resp = $rc->verify(
                $post['g-recaptcha-response'],
                $_SERVER['REMOTE_ADDR']
            );
            $can_comment = $resp->isSuccess();
        }

        if (!$can_comment) {
            $this->storeLensVar(
                'blog_error',
                \__('Invalid CAPTCHA Response. Please try again.')
            );
            return false;
        }
        $_POST['name'] = '';
        $_POST['email'] = '';
        $_POST['url'] = '';
        $_POST['message'] = '';

        return $this->blog->addCommentToPost(
            $post,
            $blogPostId,
            $published
        );
    }

    /**
     * List all of the blog posts
     *
     * @route blog/all
     * @throws EmulatePageNotFound
     */
    public function listAll()
    {
        if (!$this->can('read')) {
            throw new EmulatePageNotFound();
        }
        $blogRoll = $this->blog->listAllPublic();

        $mathJAX = false;
        foreach ($blogRoll as $i => $blog) {
            $blogRoll[$i] = $this->blog->getSnippet($blog);
            if (\strlen($blogRoll[$i]['snippet']) !== \strlen($blog['body'])) {
                $blogRoll[$i]['snippet'] = \rtrim($blogRoll[$i]['snippet'], "\n");
            }
            $mathJAX = $mathJAX || \strpos($blog['body'], '$$') !== false;
        }
        $args = [
            'pageTitle' => 'All Blog Posts',
            'blogRoll' => $blogRoll,
            'mathjax' => $mathJAX
        ];
        $this->config('blog.cache-lists')
            ? $this->stasis('blog/all', $args)
            : $this->lens('blog/all', $args);
    }

    /**
     * List all of the blog posts for a given year
     *
     * @route blog/author/{slug}/{page}
     * @param string $slug
     * @param string $page
     * @throws EmulatePageNotFound
     */
    public function listByAuthor(string $slug, string $page = '')
    {
        if (!$this->can('read')) {
            throw new EmulatePageNotFound();
        }
        list($offset, $limit) = $this->getOffsetAndLimit($page);

        $author = $this->blog->getAuthorBySlug($slug);
        if (empty($author)) {
            throw new EmulatePageNotFound();
        }

        $count = $this->blog->countByAuthor((int) $author['authorid']);
        $blogRoll = $this->blog->listByAuthor(
            (int) $author['authorid'],
            $limit,
            $offset
        );

        $mathJAX = false;
        foreach ($blogRoll as $i => $blog) {
            $blogRoll[$i] = $this->blog->getSnippet($blog);
            if (\strlen($blogRoll[$i]['snippet']) !== \strlen($blog['body'])) {
                $blogRoll[$i]['snippet'] = \rtrim($blogRoll[$i]['snippet'], "\n");
            }
            $mathJAX = $mathJAX || \strpos($blog['body'], '$$') !== false;
        }
        $args = [
            'author' => $author,
            'blogroll' => $blogRoll,
            'mathjax' => $mathJAX,
            'pagination' => [
                'base' => '/blog/author/' . $slug,
                'count' => $count,
                'page' => (int) \ceil($offset / ($limit ?? 1)) + 1,
                'per_page' => $limit
            ]
        ];
        $this->config('blog.cache-lists')
            ? $this->stasis('blog/author', $args)
            : $this->lens('blog/author', $args);
    }

    /**
     * List all of the blog posts for a given year
     *
     * @route blog/category/{slug}/{page}
     * @param string $slug
     * @param string $page
     * @throws EmulatePageNotFound
     */
    public function listByCategory(string $slug = '', string $page = '')
    {
        list($offset, $limit) = $this->getOffsetAndLimit($page);

        if (!empty($slug)) {
            $category = $this->blog->getCategory($slug);
            if (empty($category)) {
                throw new EmulatePageNotFound();
            }
            $cats = $this->blog->expandCategory($category['categoryid']);
            if (!\in_array($category['categoryid'], $cats)) {
                \array_unshift($cats, $category['categoryid']);
            }
        } else {
            $category = [
                'name' => 'Uncategorized'
            ];
            $cats = [];
        }

        $count = $this->blog->countByCategories($cats);
        $blogRoll = $this->blog->listByCategories($cats, $limit, $offset);

        $mathJAX = false;
        foreach ($blogRoll as $i => $blog) {
            $blogRoll[$i] = $this->blog->getSnippet($blog);
            if (\strlen($blogRoll[$i]['snippet']) !== \strlen($blog['body'])) {
                $blogRoll[$i]['snippet'] = \rtrim($blogRoll[$i]['snippet'], "\n");
            }
            $mathJAX = $mathJAX || \strpos($blog['body'], '$$') !== false;
        }

        $args = [
            'category' => $category,
            'blogroll' => $blogRoll,
            'mathjax' => $mathJAX,
            'pagination' => [
                'base' => '/blog/category/' . $slug,
                'count' => $count,
                'page' => (int) \ceil($offset / ($limit ?? 1)) + 1,
                'per_page' => $limit
            ]
        ];
        $this->config('blog.cache-lists')
            ? $this->stasis('blog/category', $args)
            : $this->lens('blog/category', $args);
    }

    /**
     * List all of the blog posts for a given year
     *
     * @route blog/series
     */
    public function listSeries()
    {
        list($offset, $limit) = $this->getOffsetAndLimit();

        $count = $this->blog->countSeries();
        $series_items = $this->blog->listBaseSeries($limit, $offset);

        $args = [
            'series' => [
                'name' => \__('Series Index')
            ],
            'pageTitle' => \__('Series Index'),
            'series_items' => $series_items,
            'pagination' => [
                'base' => '/blog/series/',
                'suffix' => '/?page=',
                'count' => $count,
                'page' => (int) \ceil($offset / ($limit ?? 1)) + 1,
                'per_page' => $limit
            ]
        ];
        $this->config('blog.cache-lists')
            ? $this->stasis('blog/series', $args)
            : $this->lens('blog/series', $args);
    }

    /**
     * List all of the blog posts for a given year
     *
     * @route blog/series/{slug}/{page}
     * @param string $slug
     * @param string $page
     */
    public function listBySeries(string $slug = '', string $page = '')
    {
        list($offset, $limit) = $this->getOffsetAndLimit($page);

        $series = $this->blog->getSeriesInfo($slug);
        $count = $this->blog->countBySeries((int) $series['seriesid']);
        $series_items = $this->blog->listBySeries(
            (int) $series['seriesid'],
            $limit,
            $offset
        );

        $args = [
            'series' => $series,
            'pageTitle' => $series['name'],
            'series_items' => $series_items,
            'pagination' => [
                'base' => '/blog/series/' . $slug,
                'count' => $count,
                'page' => (int) \ceil($offset / ($limit ?? 1)) + 1,
                'per_page' => $limit
            ]
        ];

        $this->config('blog.cache-lists')
            ? $this->stasis('blog/series', $args)
            : $this->lens('blog/series', $args);
    }

    /**
     * List all of the blog posts for a given tag
     *
     * @route blog/tag/{slug}/{page}
     * @param string $slug
     * @param string $page
     */
    public function listByTag(string $slug, string $page = '')
    {
        list($offset, $limit) = $this->getOffsetAndLimit($page);

        $tag = $this->blog->getTag($slug);

        $count = $this->blog->countByTag((int) $tag['tagid']);
        $blogRoll = $this->blog->listByTag(
            (int) $tag['tagid'],
            $limit,
            $offset
        );

        $mathJAX = false;
        foreach ($blogRoll as $i => $blog) {
            $blogRoll[$i] = $this->blog->getSnippet($blog);
            if (\strlen($blogRoll[$i]['snippet']) !== \strlen($blog['body'])) {
                $blogRoll[$i]['snippet'] = \rtrim($blogRoll[$i]['snippet'], "\n");
            }
            $mathJAX = $mathJAX || \strpos($blog['body'], '$$') !== false;
        }

        $args = [
            'blogroll' => $blogRoll,
            'pageTitle' => \__('Blog Posts Tagged "%s"', 'default', $tag['name']),
            'mathjax' => $mathJAX,
            'pagination' => [
                'base' => '/blog/tag/' . $slug,
                'count' => $count,
                'page' => (int) \ceil($offset / ($limit ?? 1)) + 1,
                'per_page' => $limit
            ]
        ];
        $this->config('blog.cache-lists')
            ? $this->stasis('blog/tag', $args)
            : $this->lens('blog/tag', $args);
    }

    /**
     * List all of the blog posts for a given year/month
     *
     * @route blog/{year}/{month}
     * @param string $year
     * @param string $month
     */
    public function listMonth(string $year, string $month)
    {
        $count = $this->blog->countByMonth($year, $month);
        list($offset, $limit) = $this->getOffsetAndLimit();
        $blogRoll = $this->blog->listByMonth($year, $month, $limit, $offset);

        $mathJAX = false;
        foreach ($blogRoll as $i => $blog) {
            $blogRoll[$i] = $this->blog->getSnippet($blog);
            if (\strlen($blogRoll[$i]['snippet']) !== \strlen($blog['body'])) {
                $blogRoll[$i]['snippet'] = \rtrim($blogRoll[$i]['snippet'], "\n");
            }
            $mathJAX = $mathJAX || \strpos($blog['body'], '$$') !== false;
        }
        $dt = new \DateTime("{$year}-{$month}-01");
        $page = (int) \ceil($offset / ($limit ?? 1)) + 1;

        $args = [
            'blogroll' => $blogRoll,
            'mathjax' => $mathJAX,
            'pageTitle' => \__(
                'Blog Posts in %s %s (Page %d)', 'default',
                $dt->format('F'),
                $dt->format('Y'),
                $page
            ),
            'pagination' => [
                'base' => '/blog/' . $year . '/' . $month,
                'suffix' => '/?page=',
                'count' => $count,
                'page' => $page,
                'per_page' => $limit
            ]
        ];
        $this->config('blog.cache-lists')
            ? $this->stasis('blog/list', $args)
            : $this->lens('blog/list', $args);
    }

    /**
     * List all of the blog posts for a given year
     * @param string $year
     * @route blog/{year}
     */
    public function listYear(string $year)
    {
        list($offset, $limit) = $this->getOffsetAndLimit();
        $count = $this->blog->countByYear($year);
        $blogRoll = $this->blog->listByYear($year, $limit, $offset);
        $mathJAX = false;
        foreach ($blogRoll as $i => $blog) {
            $blogRoll[$i] = $this->blog->getSnippet($blog);
            if (\strlen($blogRoll[$i]['snippet']) !== \strlen($blog['body'])) {
                $blogRoll[$i]['snippet'] = \rtrim($blogRoll[$i]['snippet'], "\n");
            }
            $mathJAX = $mathJAX || \strpos($blog['body'], '$$') !== false;
        }
        $dt = new \DateTime("{$year}-01-01");
        $page = (int) \ceil($offset / ($limit ?? 1)) + 1;

        $args = [
            'blogroll' => $blogRoll,
            'mathjax' => $mathJAX,
            'pageTitle' => \__(
                'Blog Posts in the Year %s (Page %d)', 'default',
                $dt->format('Y'),
                $page
            ),
            'pagination' => [
                'base' => '/blog/' . $year,
                'suffix' => '/?page=',
                'count' => $count,
                'page' => $page,
                'per_page' => $limit
            ]
        ];

        $this->config('blog.cache-lists')
            ? $this->stasis('blog/list', $args)
            : $this->lens('blog/list', $args);
    }

    /**
     * Blog post home
     */
    public function index()
    {
        $blogRoll = $this->blog->recentFullPosts(5);
        $mathJAX = false;
        foreach ($blogRoll as $i => $blog) {
            $blogRoll[$i] = $this->blog->getSnippet($blog);
            if (\strlen($blogRoll[$i]['snippet']) !== \strlen($blog['body'])) {
                $blogRoll[$i]['snippet'] = \rtrim($blogRoll[$i]['snippet'], "\n");
            }
            $mathJAX = $mathJAX || \strpos($blog['body'], '$$') !== false;
        }

        $args = [
            'pageTitle' => \__('Blog'),
            'blogroll' => $blogRoll,
            'mathjax' => $mathJAX
        ];
        $this->config('blog.cache-lists')
            ? $this->stasis('blog/index', $args)
            : $this->lens('blog/index', $args);
    }

    /**
     * Read a blog post
     *
     * @param string $year
     * @param string $month
     * @param string $slug
     *
     * @route blog/{year}/{month}/{slug}
     */
    public function readPost(string $year, string $month, string $slug)
    {
        $blogPost = $this->blog->getBlogPost($year, $month, $slug);
        $post = $this->post();
        if ($post) {
            if ($this->addComment($post, (int) $blogPost['postid'])) {
                if (!$this->isLoggedIn()) {
                    $this->storeLensVar(
                        'blog_success',
                        \__('Your comment has been submitted successfully, but it will not appear it has been approved by the crew.')
                    );
                }
                unset($_POST['name']);
                unset($_POST['email']);
                unset($_POST['url']);
                unset($_POST['message']);
                $_POST = [];
            }
        }
        $mathJAX = \strpos($blogPost['body'], '$$') !== false;

        $blogPost['series'] = $this->blog->getPostsSeries((int) $blogPost['postid']);

        $args = [
            'pageTitle' => $blogPost['title'],
            'blogpost' => $blogPost,
            'author' => $this->blog->getAuthor($blogPost['author']),
            'config' => $this->config(),
            'mathjax' => $mathJAX
        ];
        if ($blogPost['cache']) {
            $args['cached'] = true;
            $this->stasis('blog/read', $args);
        } else {
            $comments = $this->blog->getCommentTree((int) $blogPost['postid']);
            $args['comments'] = $comments;
            $this->lens('blog/read', $args);
        }
    }

    /**
     * Gets [offset, limit] based on Blog configuration
     *
     * @param mixed $page
     * @return int[]
     */
    protected function getOffsetAndLimit($page = null): array
    {
        $per_page = $this->config('blog.per_page') ?? 20;
        $page = (int) (!empty($page) ? $page : ($_GET['page'] ?? 0));
        if ($page < 1) {
            $page = 1;
        }
        return [($page - 1) * $per_page, $per_page];
    }
}
