<?php
declare(strict_types=1);
namespace Airship\Cabin\Hull\Landing;

use \Airship\Alerts\Router\EmulatePageNotFound;
use \Airship\Alerts\Security\UserNotLoggedIn;
use \ReCaptcha\ReCaptcha;

require_once __DIR__.'/gear.php';

class BlogPosts extends LandingGear
{
    /**
     * This function is called after the dependencies have been injected by
     * AutoPilot. Think of it as a userland constructor.
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
    protected function addComment(array $post = [], int $blogPostId = 0): bool
    {
        if (!$this->config('blog.comments.enabled')) {
            $this->storeLensVar('blog_error', 'Comments are not enabled on this blog.');
            return false;
        }
        if (!$this->isLoggedIn() && !$this->config('blog.comments.guests')) {
            $this->storeLensVar('blog_error', 'Guest comments are not enabled on this blog.');
            return false;
        }
        if (!$this->isLoggedIn() && (empty($post['name']) || empty($post['email']))) {
            $this->storeLensVar('blog_error', 'Name and email address are required fields.');
            return false;
        }
        if ($this->isLoggedIn() && !$this->isSuperUser()) {
            $allowedAuthors = $this->blog->getAuthorsForUser($this->getActiveUserId());
            if (!\in_array($post['author'], $allowedAuthors)) {
                $this->storeLensVar('blog_error', 'You do not have permission to post as this author.');
                return false;
            }
        }

        $published = false;
        $can_comment = false;
        if ($this->can('publish')) {
            $published = true;
            $can_comment = true;
        } elseif (isset($post['g-recaptcha-response'])) {
            $rc = \Airship\getReCaptcha(
                $this->config('recaptcha.secret-key'),
                $this->config('recaptcha.curl-opts') ?? []
            );
            $resp = $rc->verify($post['g-recaptcha-response'], $_SERVER['REMOTE_ADDR']);
            $can_comment = $resp->isSuccess();
        }

        if (!$can_comment) {
            $this->storeLensVar('blog_error', 'Invalid CAPTCHA Response. Please try again.');
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
     * @throws EmulatePageNotFound
     */
    public function listAll()
    {
        if (!$this->can('read')) {
            throw new EmulatePageNotFound();
        }
        $blogroll = $this->blog->listAllPublic();

        $mathjax = false;
        foreach ($blogroll as $i => $blog) {
            $blogroll[$i] = $this->blog->getSnippet($blog);
            if (\strlen($blogroll[$i]['snippet']) !== \strlen($blog['body'])) {
                $blogroll[$i]['snippet'] = \rtrim($blogroll[$i]['snippet'], "\n");
            }
            $mathjax = $mathjax || \strpos($blog['body'], '$$') !== false;
        }
        $this->stasis('blog/all', [
            'pageTitle' => 'All Blog Posts',
            'blogroll' => $blogroll,
            'mathjax' => $mathjax
        ]);
    }

    /**
     * List all of the blog posts for a given year
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

        $count = $this->blog->countByAuthor($author['authorid']);
        $blogroll = $this->blog->listByAuthor($author['authorid'], $limit, $offset);

        $mathjax = false;
        foreach ($blogroll as $i => $blog) {
            $blogroll[$i] = $this->blog->getSnippet($blog);
            if (\strlen($blogroll[$i]['snippet']) !== \strlen($blog['body'])) {
                $blogroll[$i]['snippet'] = \rtrim($blogroll[$i]['snippet'], "\n");
            }
            $mathjax = $mathjax || \strpos($blog['body'], '$$') !== false;
        }

        $this->lens('blog/author', [
            'author' => $author,
            'blogroll' => $blogroll,
            'mathjax' => $mathjax,
            'pagination' => [
                'base' => '/blog/author/' . $slug,
                'count' => $count,
                'page' => (int) \ceil($offset / ($limit ?? 1)) + 1,
                'per_page' => $limit
            ]
        ]);
    }

    /**
     * List all of the blog posts for a given year
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
        $blogroll = $this->blog->listByCategories($cats, $limit, $offset);

        $mathjax = false;
        foreach ($blogroll as $i => $blog) {
            $blogroll[$i] = $this->blog->getSnippet($blog);
            if (\strlen($blogroll[$i]['snippet']) !== \strlen($blog['body'])) {
                $blogroll[$i]['snippet'] = \rtrim($blogroll[$i]['snippet'], "\n");
            }
            $mathjax = $mathjax || \strpos($blog['body'], '$$') !== false;
        }

        $this->lens('blog/category', [
            'category' => $category,
            'blogroll' => $blogroll,
            'mathjax' => $mathjax,
            'pagination' => [
                'base' => '/blog/category/' . $slug,
                'count' => $count,
                'page' => (int) \ceil($offset / ($limit ?? 1)) + 1,
                'per_page' => $limit
            ]
        ]);
    }

    /**
     * List all of the blog posts for a given year
     */
    public function listSeries()
    {
        list($offset, $limit) = $this->getOffsetAndLimit();

        $count = $this->blog->countSeries();
        $series_items = $this->blog->listBaseSeries($limit, $offset);

        $this->lens('blog/series', [
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
        ]);
    }

    /**
     * List all of the blog posts for a given year
     * @param string $slug
     * @param string $page
     */
    public function listBySeries(string $slug = '', string $page = '')
    {
        list($offset, $limit) = $this->getOffsetAndLimit($page);

        $series = $this->blog->getSeriesInfo($slug);
        $count = $this->blog->countBySeries($series['seriesid']);
        $series_items = $this->blog->listBySeries($series['seriesid'], $limit, $offset);

        # \Airship\json_response($series_items);

        $this->lens('blog/series', [
            'series' => $series,
            'pageTitle' => $series['name'],
            'series_items' => $series_items,
            'pagination' => [
                'base' => '/blog/series/' . $slug,
                'count' => $count,
                'page' => (int) \ceil($offset / ($limit ?? 1)) + 1,
                'per_page' => $limit
            ]
        ]);
    }

    /**
     * List all of the blog posts for a given tag
     *
     * @param string $slug
     * @param string $page
     */
    public function listByTag(string $slug, string $page = '')
    {
        list($offset, $limit) = $this->getOffsetAndLimit($page);

        $tag = $this->blog->getTag($slug);

        $count = $this->blog->countByTag($tag['tagid']);
        $blogroll = $this->blog->listByTag($tag['tagid'], $limit, $offset);

        $mathjax = false;
        foreach ($blogroll as $i => $blog) {
            $blogroll[$i] = $this->blog->getSnippet($blog);
            if (\strlen($blogroll[$i]['snippet']) !== \strlen($blog['body'])) {
                $blogroll[$i]['snippet'] = \rtrim($blogroll[$i]['snippet'], "\n");
            }
            $mathjax = $mathjax || \strpos($blog['body'], '$$') !== false;
        }

        $this->lens('blog/tag', [
            'blogroll' => $blogroll,
            'pageTitle' => \__('Blog Posts Tagged "%s"', 'default', $tag['name']),
            'mathjax' => $mathjax,
            'pagination' => [
                'base' => '/blog/tag/' . $slug,
                'count' => $count,
                'page' => (int) \ceil($offset / ($limit ?? 1)) + 1,
                'per_page' => $limit
            ]
        ]);
    }

    /**
     * List all of the blog posts for a given year/month
     *
     * @param string $year
     * @param string $month
     */
    public function listMonth(string $year, string $month)
    {
        $count = $this->blog->countByMonth($year, $month);
        list($offset, $limit) = $this->getOffsetAndLimit();
        $blogroll = $this->blog->listByMonth($year, $month, $limit, $offset);

        $mathjax = false;
        foreach ($blogroll as $i => $blog) {
            $blogroll[$i] = $this->blog->getSnippet($blog);
            if (\strlen($blogroll[$i]['snippet']) !== \strlen($blog['body'])) {
                $blogroll[$i]['snippet'] = \rtrim($blogroll[$i]['snippet'], "\n");
            }
            $mathjax = $mathjax || \strpos($blog['body'], '$$') !== false;
        }
        $dt = new \DateTime("{$year}-{$month}-01");
        $page = (int) \ceil($offset / ($limit ?? 1)) + 1;

        $this->lens('blog/list', [
            'blogroll' => $blogroll,
            'mathjax' => $mathjax,
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
        ]);
    }

    /**
     * List all of the blog posts for a given year
     * @param string $year
     */
    public function listYear(string $year)
    {
        list($offset, $limit) = $this->getOffsetAndLimit();
        $count = $this->blog->countByYear($year);
        $blogroll = $this->blog->listByYear($year, $limit, $offset);
        $mathjax = false;
        foreach ($blogroll as $i => $blog) {
            $blogroll[$i] = $this->blog->getSnippet($blog);
            if (\strlen($blogroll[$i]['snippet']) !== \strlen($blog['body'])) {
                $blogroll[$i]['snippet'] = \rtrim($blogroll[$i]['snippet'], "\n");
            }
            $mathjax = $mathjax || \strpos($blog['body'], '$$') !== false;
        }
        $dt = new \DateTime("{$year}-01-01");
        $page = (int) \ceil($offset / ($limit ?? 1)) + 1;

        $this->lens('blog/list', [
            'blogroll' => $blogroll,
            'mathjax' => $mathjax,
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
        ]);
    }

    public function index()
    {
        $blogroll = $this->blog->recentFullPosts(5);
        $mathjax = false;
        foreach ($blogroll as $i => $blog) {
            $blogroll[$i] = $this->blog->getSnippet($blog);
            if (\strlen($blogroll[$i]['snippet']) !== \strlen($blog['body'])) {
                $blogroll[$i]['snippet'] = \rtrim($blogroll[$i]['snippet'], "\n");
            }
            $mathjax = $mathjax || \strpos($blog['body'], '$$') !== false;
        }

        $this->lens('blog/index', [
            'pageTitle' => \__('Blog'),
            'blogroll' => $blogroll,
            'mathjax' => $mathjax
        ]);
    }

    /**
     * Read a blog post
     *
     * @param string $year
     * @param string $month
     * @param string $slug
     */
    public function readPost(string $year, string $month, string $slug)
    {
        $blogpost = $this->blog->getBlogPost($year, $month, $slug);
        if ($post = $this->post()) {
            if ($this->addComment($post, (int) $blogpost['postid'])) {
                if (!$this->isLoggedIn()) {
                    $this->storeLensVar(
                        'blog_success',
                        'Your comment has been submitted successfully, but it will not appear it has been approved by the crew.'
                    );
                }
            }
        }
        $mathjax = \strpos($blogpost['body'], '$$') !== false;

        $blogpost['series'] = $this->blog->getPostsSeries((int) $blogpost['postid']);
        $comments =  $this->blog->getCommentTree($blogpost['postid']);

        $this->lens('blog/read', [
            'pageTitle' => $blogpost['title'],
            'blogpost' => $blogpost,
            'comments' => $comments,
            'author' => $this->blog->getAuthor($blogpost['author']),
            'config' => $this->config(),
            'mathjax' => $mathjax
        ]);
    }

    /**
     * Gets [offset, limit] based on Blog configuration
     *
     * @param mixed $page
     * @return int[]
     */
    protected function getOffsetAndLimit($page = null)
    {
        $per_page = $this->config('blog.per_page') ?? 20;
        $page = (int) (!empty($page) ? $page : ($_GET['page'] ?? 0));
        if ($page < 1) {
            $page = 1;
        }
        return [($page - 1) * $per_page, $per_page];
    }
}
