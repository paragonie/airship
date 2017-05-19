<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Controller;

use Airship\Cabin\Bridge\Model as BP;
use Airship\Cabin\Bridge\Filter\Blog\{
    CommentFilter,
    DeleteCategoryFilter,
    DeletePostFilter,
    DeleteSeriesFilter,
    EditCategoryFilter,
    EditPostFilter,
    EditSeriesFilter,
    EditTagFilter,
    NewCategoryFilter,
    NewPostFilter,
    NewSeriesFilter,
    NewTagFilter
};
use Airship\Engine\Bolt\Orderable;
use Airship\Engine\Security\Util;

require_once __DIR__.'/init_gear.php';

/**
 * Class Blog
 * @package Airship\Cabin\Bridge\Controller
 */
class Blog extends LoggedInUsersOnly
{
    use Orderable;

    /**
     * @var BP\Author
     */
    protected $author;

    /**
     * @var BP\Blog
     */
    protected $blog;

    /**
     * This function is called after the dependencies have been injected by
     * AutoPilot. Think of it as a user-land constructor.
     */
    public function airshipLand()
    {
        parent::airshipLand();
        $this->blog = $this->model('Blog');
        $this->author = $this->model('Author');
        $this->storeViewVar('active_submenu', 'Blog');
    }

    /**
     * Blog management landing page
     *
     * @route blog
     */
    public function index()
    {
        $this->view('blog/index');
    }

    /**
     * Delete a category
     *
     * @route blog/category/delete/{id}
     * @param string $id
     */
    public function deleteCategory(string $id = '')
    {
        $id = (int) $id;

        $post = $this->post(new DeleteCategoryFilter());
        if ($post) {
            if ($this->processDeleteCategory($id, $post)) {
                \Airship\redirect(
                    $this->airship_cabin_prefix . '/blog/category'
                );
            }
        }
        $category = $this->blog->getCategoryInfo($id);

        $this->view(
            'blog/category_delete',
            [
                'active_link' => 'bridge-link-blog-category',
                'category' => $category,
                'categories' => $this->blog->getCategoryTree(),
                'title' => \__('Edit Category "%s"', 'default',
                    Util::noHTML($category['name'])
                )
            ]
        );

    }

    /**
     * Delete a blog post
     *
     * @route blog/post/delete/{id}
     * @param string $id
     */
    public function deletePost(string $id)
    {
        $id = (int) $id;

        // Load Data
        $blogPost = $this->blog->getBlogPostById($id);
        $blogPost['tags'] = $this->blog->getTagsForPost($id);
        $latestVersion = $this->blog->getBlogPostLatestVersion($id);

        if ($this->isSuperUser()) {
            $authors = $this->author->getAllPreferMine($this->getActiveUserId());
        } else {
            $authors = $this->author->getForUser(
                $this->getActiveUserId()
            );
        }
        $authorsAllowed = [];
        foreach ($authors as $a) {
            $authorsAllowed[] = (int) $a['authorid'];
        }

        // The 'delete' permission here means "delete any", not just "delete mine":
        if (!$this->can('delete')) {
            // Does this author belong to you?
            if (!\in_array((int) $blogPost['author'], $authorsAllowed)) {
                // No? Then you don't belong here
                \Airship\redirect(
                    $this->airship_cabin_prefix . '/blog/post'
                );
            }
        }

        $post = $this->post(new DeletePostFilter());
        if (!empty($post)) {
            if ($this->processDeletePost($post, $authorsAllowed, $blogPost)) {
                \Airship\redirect(
                    $this->airship_cabin_prefix . '/blog/post'
                );
            }
            $this->storeViewVar('form_error', \__('An error has occurred.'));
        }
        $this->view(
            'blog/posts_delete',
            [
                'active_link' => 'bridge-link-blog-posts',
                'blogpost' => $blogPost,
                'latest' => $latestVersion
            ]
        );
    }

    /**
     * Delete a series.
     *
     * @param string $id
     */
    public function deleteSeries(string $id = '')
    {
        $id = (int) $id;
        $post = $this->post(new DeleteSeriesFilter());
        if (!empty($post)) {
            if ($this->blog->deleteSeries($id)) {
                \Airship\redirect(
                    $this->airship_cabin_prefix . '/blog/series'
                );
            }
        }
        $series = $this->blog->getSeries($id);
        $this->view(
            'blog/series_delete',
            [
                'active_link' =>
                    'bridge-link-blog-series',
                'series' =>
                    $series
            ]
        );
    }

    /**
     * Edit a category
     *
     * @route blog/category/edit/{id}
     * @param string $id
     */
    public function editCategory(string $id = '')
    {
        $id = (int) $id;
        $post = $this->post(new EditCategoryFilter());
        if (!empty($post)) {
            if ($this->blog->updateCategory($id, $post)) {
                \Airship\redirect(
                    $this->airship_cabin_prefix . '/blog/category'
                );
            }
        }
        $category = $this->blog->getCategoryInfo($id);

        $this->view(
            'blog/category_edit',
            [
                'active_link' => 'bridge-link-blog-category',
                'category' => $category,
                'categories' => $this->blog->getCategoryTree(),
                'title' => \__('Edit Category "%s"', 'default',
                    Util::noHTML($category['name'])
                )
            ]
        );
    }

    /**
     * Edit a blog post
     *
     * @route blog/post/edit/{id}
     * @param string $id
     */
    public function editPost(string $id)
    {
        $id = (int) $id;
        // Load Data
        $blogPost = $this->blog->getBlogPostById($id);
        $blogPost['tags'] = $this->blog->getTagsForPost($id);
        $latestVersion = $this->blog->getBlogPostLatestVersion($id);

        if ($this->isSuperUser()) {
            $authors = $this->author->getAllPreferMine($this->getActiveUserId());
        } else {
            $authors = $this->author->getForUser(
                $this->getActiveUserId()
            );
        }
        $authorsAllowed = [];
        foreach ($authors as $a) {
            $authorsAllowed[] = (int) $a['authorid'];
        }
        // The 'update' permission here means "update any", not just "update mine":
        if (!$this->can('update')) {
            // Does this author belong to you?
            if (!\in_array((int) $blogPost['author'], $authorsAllowed)) {
                // No? Then you don't belong here
                \Airship\redirect($this->airship_cabin_prefix . '/blog/post');
            }
        }
        $categories = $this->blog->getCategoryTree();
        $tags = $this->blog->getTags();

        $post = $this->post(new EditPostFilter());
        if (!empty($post)) {
            if ($this->processEditPost($post, $authorsAllowed, $blogPost)) {
                \Airship\redirect(
                    $this->airship_cabin_prefix . '/blog/post'
                );
            }
        }
        $this->view(
            'blog/posts_edit',
            [
                'active_link' => 'bridge-link-blog-posts',
                'blogpost' => $blogPost,
                'latest' => $latestVersion,
                'authors' => $authors,
                'categories' => $categories,
                'tags' => $tags,
                'title' => \__('Edit Blog Post "%s"', 'default',
                    Util::noHTML($blogPost['title'])
                )
            ]
        );
    }

    /**
     * @route blog/series/edit/{id}
     *
     * @param string $seriesId
     */
    public function editSeries(string $seriesId)
    {
        $seriesId = (int) $seriesId;
        $author = null;

        $series = $this->blog->getSeries($seriesId);
        if (!empty($series['config'])) {
            $series['config'] = \json_decode($series['config'], true);
        } else {
            $series['config'] = [];
        }
        $series_items = $this->blog->getSeriesItems($seriesId);
        $authorsAllowed = [];
        // Load Data
        if ($this->isSuperUser()) {
            $authors = $this->author->getAll();
            foreach ($authors as $a) {
                $authorsAllowed[] = (int) $a['authorid'];
                if ($a['authorid'] === $series['author']) {
                    $author = $a;
                    break;
                }
            }
        } else {
            $authors = $this->author->getForUser(
                $this->getActiveUserId()
            );
            foreach ($authors as $a) {
                $authorsAllowed[] = (int) $a['authorid'];
                if ($a['authorid'] === $series['author']) {
                    $author = $a;
                    break;
                }
            }
            if (!\in_array((int) $series['author'], $authorsAllowed)) {
                // You are not allowed
                \Airship\redirect($this->airship_cabin_prefix . '/blog/series');
            }
        }

        $post = $this->post(new EditSeriesFilter());
        if (!empty($post)) {
            if ($this->processEditSeries(
                $post,
                $seriesId,
                $this->flattenOld($series_items)
            )) {
                \Airship\redirect(
                    $this->airship_cabin_prefix . '/blog/series'
                );
            }
        }
        $this->view(
            'blog/series_edit',
            [
                'active_link' => 'bridge-link-blog-series',
                'series' => $series,
                'series_items' => $series_items,
                'authors' => $authors,
                'author' => $author,
                'title' => \__('Edit Blog Series "%s"', 'default',
                    Util::noHTML($series['name'])
                )
            ]
        );
    }

    /**
     * Edit a tag
     *
     * @route blog/tag/edit/{id}
     * @param string $id
     */
    public function editTag(string $id = '')
    {
        $id = (int) $id;
        if (!$this->can('update')) {
            \Airship\redirect($this->airship_cabin_prefix . '/blog/tag');
        }
        $tag = $this->blog->getTagInfo($id);

        $post = $this->post(new EditTagFilter());
        if (!empty($post)) {
            if ($this->processEditTag($id, $post)) {
                \Airship\redirect(
                    $this->airship_cabin_prefix . '/blog/tag'
                );
            }
        }
        $this->view(
            'blog/tags_edit',
            [
                'active_link' => 'bridge-link-blog-tags',
                'tag' => $tag,
                'title' => \__('Edit Blog Tag "%s"', 'default',
                    Util::noHTML($tag['name'])
                )
            ]
        );
    }

    /**
     * List the categories
     *
     * @route blog/category{_page}
     */
    public function listCategories()
    {
        $this->view(
            'blog/category',
            [
                'active_link' => 'bridge-link-blog-category',
                'categories' => $this->blog->getCategoryTree()
            ]
        );
    }

    /**
     * @route blog/comments{_page}
     * @param string $page
     */
    public function listComments($page = null)
    {
        if (!$this->can('publish')) {
            \Airship\redirect($this->airship_cabin_prefix . '/blog');
        }
        list($offset, $limit) = $this->getOffsetAndLimit($page);

        $this->view(
            'blog/comments',
            [
                'active_link' => 'bridge-link-blog-comments',
                'comments' => $this->blog->listComments($offset, $limit),
                'pagination' => [
                    'base' => $this->airship_cabin_prefix . '/blog/post',
                    'suffix' => '/',
                    'count' => $this->blog->numComments(),
                    'page' => (int) \ceil($offset / ($limit ?? 1)) + 1,
                    'per_page' => $limit
                ]
            ]
        );
    }

    /**
     * List the blog posts
     *
     * @route blog/post{_page}
     * @param string $page
     */
    public function listPosts($page = null)
    {
        list($offset, $limit) = $this->getOffsetAndLimit($page);

        $this->view(
            'blog/posts',
            [
                'active_link' => 'bridge-link-blog-posts',
                'blog_posts' => $this->blog->listPosts(
                    $this->isSuperUser(),
                    $offset,
                    $limit
                ),
                'pagination' => [
                    'base' => $this->airship_cabin_prefix . '/blog/post',
                    'suffix' => '/',
                    'count' => $this->blog->numPosts(),
                    'page' => (int) \ceil($offset / ($limit ?? 1)) + 1,
                    'per_page' => $limit
                ]
            ]
        );
    }

    /**
     * @route blog/series{_page}
     * @param mixed $page
     */
    public function listSeries($page = null)
    {
        list($offset, $limit) = $this->getOffsetAndLimit($page);
        if ($this->isSuperUser()) {
            $series = $this->blog->getAllSeries($offset, $limit);
            $count = $this->blog->numSeries();
        } else {
            $userId = $this->getActiveUserId();
            $series = $this->blog->getSeriesForUser(
                $userId,
                $offset,
                $limit
            );
            $count = $this->blog->numSeriesForUser($userId);
        }
        $authors = [];
        foreach ($series as $i => $s) {
            $s['seriesid'] = (int) $s['seriesid'];
            $s['author'] = (int) $s['author'];
            if (empty($authors[$s['author']]) && !empty($s['author'])) {
                $authors[$s['author']] = $this->author->getById($s['author']);
            }
            $series[$i]['author_data'] = $authors[$s['author']];
            $series[$i]['num_items'] = $this->blog->numItemsInSeries($s['seriesid']);
        }
        $this->view(
            'blog/series',
            [
                'active_link' => 'bridge-link-blog-series',
                'series' => $series,
                'pagination' => [
                    'base' => $this->airship_cabin_prefix . '/blog/series',
                    'suffix' => '/',
                    'count' => $count,
                    'page' => (int) \ceil($offset / ($limit ?? 1)) + 1,
                    'per_page' => $limit
                ]
            ]
        );
    }

    /**
     * List tags
     *
     * @route blog/tag{_page}
     * @param mixed $page
     */
    public function listTags($page = null)
    {
        list($offset, $limit) = $this->getOffsetAndLimit($page);
        list($sort, $dir) = $this->getSortArgs('name');
        $post = $this->post(new NewTagFilter());
        if (!empty($post)) {
            if ($this->blog->createTag($post)) {
                \Airship\redirect(
                    $this->airship_cabin_prefix . '/blog/tag'
                );
            }
        }

        $this->view(
            'blog/tags',
            [
                'active_link' => 'bridge-link-blog-tags',
                'tags' => $this->blog->listTags(
                    $offset,
                    $limit,
                    $sort,
                    $dir === 'DESC'
                ),
                'sort' => $sort,
                'dir' => $dir,
                'pagination' => [
                    'base' => $this->airship_cabin_prefix . '/blog/tag',
                    'suffix' => '/',
                    'extra_args' => '?sort=' . $sort . '&dir=' . $dir,
                    'count' => $this->blog->numTags(),
                    'page' => (int) \ceil($offset / ($limit ?? 1)) + 1,
                    'per_page' => $limit
                ]
            ]
        );
    }

    /**
     * Create a new category
     *
     * @route blog/category/new
     */
    public function newCategory()
    {
        if (!$this->can('create')) {
            \Airship\redirect($this->airship_cabin_prefix . '/blog/category');
        }
        $post = $this->post(new NewCategoryFilter());
        if (!empty($post)) {
            if ($this->blog->createCategory($post)) {
                \Airship\redirect(
                    $this->airship_cabin_prefix . '/blog/category'
                );
            }
        }

        $this->view('blog/category_new', [
            'active_link' => 'bridge-link-blog-category',
            'categories' => $this->blog->getCategoryTree()
        ]);
    }

    /**
     * Create a new blog post
     *
     * @route blog/post/new
     */
    public function newPost()
    {
        if (!$this->can('create')) {
            \Airship\redirect($this->airship_cabin_prefix . '/blog/post');
        }
        // Load Data
        if ($this->isSuperUser()) {
            $authors = $this->author->getAllPreferMine(
                $this->getActiveUserId()
            );
        } else {
            $authors = $this->author->getForUser(
                $this->getActiveUserId()
            );
        }
        $categories = $this->blog->getCategoryTree();
        $tags = $this->blog->getTags();

        $post = $this->post(new NewPostFilter());
        if (!empty($post)) {
            $authorsAllowed = [];
            foreach ($authors as $a) {
                $authorsAllowed[] = (int) $a['authorid'];
            }
            if ($this->processNewPost($post, $authorsAllowed)) {
                \Airship\redirect(
                    $this->airship_cabin_prefix . '/blog/post'
                );
            }
        }
        $this->view(
            'blog/posts_new', [
                'current_time' => (new \DateTime())
                    ->format(\AIRSHIP_DATE_FORMAT),
                'active_link' => 'bridge-link-blog-posts',
                'authors' => $authors,
                'categories' => $categories,
                'tags' => $tags,
            ]
        );
    }

    /**
     * Create a new blog series
     *
     * @route blog/series/new
     */
    public function newSeries()
    {
        if (!$this->can('create')) {
            \Airship\redirect($this->airship_cabin_prefix . '/blog/series');
        }
        // Load Data
        if ($this->isSuperUser()) {
            $authors = $this->author->getAllPreferMine($this->getActiveUserId());
        } else {
            $authors = $this->author->getForUser(
                $this->getActiveUserId()
            );
        }

        $post = $this->post(new NewSeriesFilter());
        if (!empty($post)) {
            $authorsAllowed = [];
            foreach ($authors as $a) {
                $authorsAllowed[] = (int) $a['authorid'];
            }
            if ($this->processNewSeries($post, $authorsAllowed)) {
                \Airship\redirect(
                    $this->airship_cabin_prefix . '/blog/series'
                );
            }
        }
        $this->view(
            'blog/series_new', [
                'active_link' => 'bridge-link-blog-series',
                'authors' => $authors
            ]
        );
    }

    /**
     * View the history for a blog post.
     *
     * @param string $postID
     *
     * @route blog/post/history/{id}
     */
    public function postHistory(string $postID = '')
    {
        $blog = $this->blog->getBlogPostById((int) $postID);
        if (!$blog || !$this->can('read')) {
            \Airship\redirect(
                $this->airship_cabin_prefix . '/blog/post'
            );
        }
        $this->view(
            'blog/post_history',
            [
                'active_link' =>
                    'bridge-link-blog-posts',
                'blog' =>
                    $blog,
                'history' =>
                    $this->blog->getBlogPostVersions((int) $postID)
            ]
        );
    }

    /**
     * Compare two versions of a blog post.
     *
     * @param string $postID
     * @param string $leftUnique
     * @param string $rightUnique
     *
     * @route blog/post/history/{id}/diff/{string}/{string}
     */
    public function postHistoryDiff(
        string $postID = '',
        string $leftUnique = '',
        string $rightUnique = ''
    ) {
        $postID = (int) $postID;
        $blog = $this->blog->getBlogPostById($postID);
        if (!$blog || !$this->can('read')) {
            \Airship\redirect(
                $this->airship_cabin_prefix . '/blog/post'
            );
        }
        $left = $this->blog->getBlogPostVersionByUniqueId($leftUnique);
        $right = $this->blog->getBlogPostVersionByUniqueId($rightUnique);

        // Sanity check. If it fails, go back to the history list.
        if ((int) $left['postid'] !== $postID || (int) $right['postid'] !== $postID) {
            \Airship\redirect(
                $this->airship_cabin_prefix . '/blog/post/history/' . $postID
            );
        }

        $this->view(
            'blog/post_history_diff',
            [
                'active_link' =>
                    'bridge-link-blog-posts',
                'blog' =>
                    $blog,
                'left' =>
                    $left,
                'right' =>
                    $right
            ]
        );
    }

    /**
     * View a version of a blog post.
     *
     * @param string $postID
     * @param string $uniqueID
     *
     * @route blog/post/history/{id}/view/{string}
     */
    public function postHistoryView(
        string $postID = '',
        string $uniqueID = ''
    ) {
        $postID = (int) $postID;
        $blog = $this->blog->getBlogPostById($postID);
        if (!$blog || !$this->can('read')) {
            \Airship\redirect(
                $this->airship_cabin_prefix . '/blog/post'
            );
        }
        $blog['tags'] = $this->blog->getTagsForPost($postID);
        $version = $this->blog->getBlogPostVersionByUniqueId($uniqueID);
        if ((int) $version['postid'] !== $postID || empty($version)) {
            \Airship\redirect(
                $this->airship_cabin_prefix . '/blog/post/history/' . $postID
            );
        }
        if ($this->isSuperUser()) {
            $authors = $this->author->getAllPreferMine($this->getActiveUserId());
        } else {
            $authors = $this->author->getForUser(
                $this->getActiveUserId()
            );
        }
        $categories = $this->blog->getCategoryTree();
        $tags = $this->blog->getTags();

        $this->view(
            'blog/post_history_view',
            [
                'active_link' =>
                    'bridge-link-blog-posts',
                'authors' =>
                    $authors,
                'blogpost' =>
                    $blog,
                'categories' =>
                    $categories,
                'tags' =>
                    $tags,
                'title' => \__('Revision for  Blog Post "%s"', 'default',
                    Util::noHTML($blog['title'])
                ),
                'prev_uniqueid' => $this->blog->getPrevVersionUniqueId(
                    $postID,
                    (int) $version['versionid']
                ),
                'next_uniqueid' => $this->blog->getNextVersionUniqueId(
                    $postID,
                    (int) $version['versionid']
                ),
                'version' =>
                    $version
            ]
        );
    }

    /**
     * View a comment
     *
     * @param string $commentId
     * @route blog/comments/view/{id}
     */
    public function viewComment(string $commentId = '')
    {
        $commentId = (int) $commentId;
        $post = $this->post(new CommentFilter());
        if (!empty($post)) {
            switch ($post['comment_btn']) {
                case 'publish':
                    if ($this->can('publish')) {
                        $this->blog->publishComment($commentId);
                    }
                    break;
                case 'hide':
                    if ($this->can('publish')) {
                        $this->blog->hideComment($commentId);
                    }
                    break;
                case 'delete':
                    if ($this->can('delete')) {
                        if ($this->blog->deleteComment($commentId)) {
                            \Airship\redirect($this->airship_cabin_prefix . '/blog/comments');
                        }
                    }
                    break;
            }
        }

        $this->view(
            'blog/comments_view', [
                'active_link' => 'bridge-link-blog-comments',
                'comment' => $this->blog->getCommentById((int) $commentId)
            ]
        );
    }

    #=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#

    /**
     * Gets [offset, limit] based on configuration
     *
     * @param string $page
     * @param int $per_page
     * @return int[]
     */
    protected function getOffsetAndLimit($page = null, int $per_page = 0)
    {
        if ($per_page === 0) {
            $per_page = (int) ($this->config('blog.per_page') ?? 20);
        }
        $page = (int) (!empty($page) ? $page : ($_GET['page'] ?? 0));
        if ($page < 1) {
            $page = 1;
        }
        return [($page - 1) * $per_page, $per_page];
    }

    // POST data processing methods below:

    /**
     * Delete a blog post category
     *
     * @param int $categoryId
     * @param array $post
     * @return bool
     */
    protected function processDeleteCategory(int $categoryId, array $post = []): bool
    {
        if (!$this->isSuperUser()) {
            if (!$this->can('delete')) {
                return false;
            }
        }

        return $this->blog->deleteCategory(
            $categoryId,
            (int) ($post['moveChildrenTo'] ?? 0)
        );
    }

    /**
     * Delete a blog post
     *
     * @param array $post
     * @param array $authorsAllowed
     * @param array $oldPost
     * @return bool
     */
    protected function processDeletePost(
        array $post,
        array $authorsAllowed = [],
        array $oldPost = []
    ): bool {
        // Extra caution: check permissions again.
        if (!$this->isSuperUser()) {
            if (!$this->can('delete')) {
                // Does this author belong to you?
                if (!\in_array((int) $oldPost['author'], $authorsAllowed)) {
                    return false;
                }
            }
        }

        return $this->blog->deletePost($post, $oldPost);
    }

    /**
     * Update a blog post
     *
     * @param array $post
     * @param array $authorsAllowed
     * @param array $oldPost
     * @return bool
     */
    protected function processEditPost(
        array $post,
        array $authorsAllowed = [],
        array $oldPost = []
    ): bool {
        $required = [
            'author',
            'blog_post_body',
            'format',
            'save_btn',
            'title'
        ];
        if (!\Airship\all_keys_exist($required, $post)) {
            return false;
        }
        if (!$this->isSuperUser()) {
            if (!empty($post['author'])) {
                // Only administrators can transfer ownership; block this request
                return false;
            }
            if (!\in_array((int) $oldPost['author'], $authorsAllowed)) {
                // This author is invalid.
                return false;
            }
        }
        $publish = $this->can('publish')
            ? ($post['save_btn'] === 'publish')
            : false;
        return $this->blog->updatePost($post, $oldPost, $publish);
    }

    /**
     * @param int $tagId
     * @param array $post
     * @return bool
     */
    protected function processEditTag(int $tagId, array $post): bool
    {
        return $this->blog->editTag($tagId, $post);
    }

    /**
     * Create a new blog post
     *
     * @param array $post
     * @param array $authorsAllowed
     * @return bool
     */
    protected function processNewPost(
        array $post,
        array $authorsAllowed = []
    ): bool {
        $required = [
            'author',
            'blog_post_body',
            'format',
            'save_btn',
            'title'
        ];
        if (!\Airship\all_keys_exist($required, $post)) {
            return false;
        }
        if (!\in_array($post['author'], $authorsAllowed)) {
            return false;
        }
        $publish = $this->can('publish')
            ? ($post['save_btn'] === 'publish')
            : false;
        return $this->blog->createPost($post, $publish);
    }

    /**
     * Update the existing series
     *
     * @param array $post
     * @param int $seriesId
     * @param array $oldItems
     * @return bool
     */
    protected function processEditSeries(
        array $post,
        int $seriesId,
        array $oldItems = []
    ): bool {
        if (!\array_key_exists('items', $post)) {
            return false;
        }
        if (!$this->isSuperUser() && isset($post['author'])) {
            unset($post['author']);
        }
        return $this->blog->updateSeries(
            $seriesId,
            $oldItems,
            $post
        );
    }

    /**
     * Convert a 2D array into a flat, ordered array of type_id
     *
     * @param array $oldItems
     * @return array
     */
    protected function flattenOld(array $oldItems): array
    {
        $items = [];
        foreach ($oldItems as $old) {
            if ($old['series']) {
                $items[] = 'series_' . $old['series'];
            } else {
                $items[] = 'blogpost_' . $old['post'];
            }
        }
        return $items;
    }

    /**
     * Create a new series
     *
     * @param array $post
     * @param array $authorsAllowed
     * @return bool
     */
    protected function processNewSeries(
        array $post = [],
        array $authorsAllowed = []
    ): bool {
        if (!\Airship\all_keys_exist(['author', 'items'], $post)) {
            return false;
        }
        if (!\in_array($post['author'], $authorsAllowed)) {
            return false;
        }
        return $this->blog->createSeries($post);
    }
}
