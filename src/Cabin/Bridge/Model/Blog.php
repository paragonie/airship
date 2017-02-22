<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Model;

use Airship\Alerts\CabinNotFound;
use Airship\Alerts\Database\DBException;
use Airship\Engine\Bolt\{
    Common,
    Orderable,
    Slug
};
use Airship\Engine\Bolt\Cache;

require_once __DIR__.'/init_gear.php';

/**
 * Class Blog
 *
 * Blog posts, categories, series, tags.
 *
 * @package Airship\Cabin\Bridge\Model
 */
class Blog extends ModelGear
{
    use Common;
    use Orderable;
    use Slug;
    use Cache;

    /**
     * @var string Cabin for this blog post (used as a default parameter)
     */
    protected $cabin = 'Hull';

    /**
     * Sanity check; don't allow a category to belong to one of its children.
     * Returns TRUE if this check is violated.
     *
     * @param int $newParent
     * @param int $categoryId
     * @return bool
     */
    public function categoryDescendsFrom(int $newParent, int $categoryId): bool
    {
        return \in_array(
            $categoryId,
            $this->getCategoryParents($newParent)
        );
    }

    /**
     * Create a new category
     *
     * @param array $post
     * @return bool
     */
    public function createCategory(array $post): bool
    {
        $this->db->beginTransaction();
        $slug = $this->makeGenericSlug($post['name'], 'hull_blog_categories');
        $this->db->insert(
            'hull_blog_categories',
            [
                'name' =>
                    $post['name'],
                'parent' =>
                    $post['parent'] > 0
                        ? $post['parent']
                        : null,
                'slug' =>
                    $slug,
                'preamble' =>
                    $post['preamble']
            ]
        );
        return $this->db->commit();
    }

    /**
     * Create (and, optionally, publish) a new blog post.
     *
     * @param array $post
     * @param bool $publish
     * @return bool
     */
    public function createPost(array $post, bool $publish = false): bool
    {
        $this->db->beginTransaction();

        $newPostArgs = [
            'author' =>
                $post['author'],
            'category' =>
                $post['category'] > 0
                    ? $post['category']
                    : null,
            'description' =>
                $post['description'],
            'format' =>
                !empty($post['format'])
                    ? $post['format']
                    : 'Rich Text',
            'shorturl' =>
                \Airship\uniqueId(),
            'status' =>
                $publish,
            'slug' =>
                $this->makeBlogPostSlug(
                    !empty($post['slug'])
                        ? $post['slug']
                        : ($post['title'] ?? 'Untitled')
                ),
            'title' =>
                $post['title'] ?? 'Untitled',
        ];

        // If we are publishing, let's set the publishing time.
        if ($publish) {
            if (!empty($post['published'])) {
                try {
                    $pub = new \DateTime($post['published']);
                } catch (\Throwable $ex) {
                    // Invalid DateTime format
                    $pub = new \DateTime();
                }
            } else {
                $pub = new \DateTime();
            }
            $newPostArgs['published'] = $pub->format(\AIRSHIP_DATE_FORMAT);
        }

        // Create the post entry
        $newPostId = $this->db->insertGet(
            'hull_blog_posts',
            $newPostArgs,
            'postid'
        );

        // Did something break?
        if ($newPostId === false) {
            return false;
        }
        if ($publish) {
            \Airship\clear_cache();
        }

        // Insert the initial blog post version
        $this->db->insert(
            'hull_blog_post_versions',
            [
                'post' =>
                    $newPostId,
                'body' =>
                    $post['blog_post_body'],
                'format' =>
                    $post['format'],
                'metadata' =>
                    \json_encode($post['metadata'] ?? []),
                'live' =>
                    $publish,
                'published_by' =>
                    $publish
                        ? $this->getActiveUserId()
                        : null

            ]
        );

        // Populate tags (only allow this if the tag actually exists)
        $allTags = $this->db->column('SELECT tagid FROM hull_blog_tags');
        foreach ($post['tags'] as $tag) {
            if (!\in_array($tag, $allTags)) {
                continue;
            }
            $this->db->insert(
                'hull_blog_post_tags',
                [
                    'postid' => $newPostId,
                    'tagid' => $tag
                ]
            );
        }

        return $this->db->commit();
    }

    /**
     * Inserts a new series, and the subsequent items, in the database
     *
     * @param array $post
     * @return bool
     */
    public function createSeries(array $post): bool
    {
        $this->db->beginTransaction();

        $series = $this->db->insertGet(
            'hull_blog_series',
            [
                'name' =>
                    $post['name'],
                'author' =>
                    $post['author'],
                'slug' =>
                    $this->makeGenericSlug($post['name'], 'hull_blog_series'),
                'preamble' =>
                    $post['preamble'] ?? '',
                'format' =>
                    $post['format'] ?? 'Rich Text',
                'config' =>
                    $post['config']
                        ? \json_encode($post['config'])
                        : '[]'
            ],
            'seriesid'
        );
        $insert = [
            'parent' => $series
        ];

        $listOrder = 0;
        foreach (\explode(',', $post['items']) as $item) {
            if (\strpos($item, '_') === false) {
                continue;
            }
            list ($type, $itemId) = \explode('_', $item);
            if ($type === 'series') {
                $_insert = $insert;
                $_insert['series'] = (int) $itemId;
            } elseif ($type === 'blogpost') {
                $_insert = $insert;
                $_insert['post'] = (int) $itemId;
            } else {
                continue;
            }
            $_insert['listorder'] = ++$listOrder;

            $this->db->insert('hull_blog_series_items', $_insert);
        }
        return $this->db->commit();
    }

    /**
     * Create a new tag
     *
     * @param array $post
     * @return bool
     */
    public function createTag(array $post): bool
    {
        $this->db->beginTransaction();
        $slug = $this->makeGenericSlug($post['name'], 'hull_blog_tags');
        $this->db->insert(
            'hull_blog_tags',
            [
                'name' => $post['name'],
                'slug' => $slug
            ]
        );
        return $this->db->commit();
    }

    /**
     * Delete a category and move all related content
     *
     * @param int $categoryID
     * @param int $moveChildrenTo
     * @return bool
     */
    public function deleteCategory(int $categoryID, int $moveChildrenTo = 0): bool
    {
        if ($moveChildrenTo <= 0) {
            $moveChildrenTo = null;
        }
        $this->db->beginTransaction();
        try {
            $this->db->update(
                'hull_blog_posts',
                [
                    'category' =>
                        $moveChildrenTo
                ],
                [
                    'category' =>
                        $categoryID
                ]
            );
            $this->db->delete(
                'hull_blog_categories',
                [
                    'categoryid' =>
                        $categoryID
                ]
            );
        } catch (DBException $ex) {
            $this->db->rollBack();
            return false;
        }
        \Airship\clear_cache();
        return $this->db->commit();
    }

    /**
     * Delete this comment and all of its revision history.
     *
     * @param int $commentId
     * @return bool
     */
    public function deleteComment(int $commentId): bool
    {
        $this->db->beginTransaction();
        $this->db->delete(
            'hull_blog_comment_versions',
            [
                'comment' => $commentId
            ]
        );
        $this->db->delete(
            'hull_blog_comments',
            [
                'commentid' => $commentId
            ]
        );
        return $this->db->commit();
    }

    /**
     * Delete a blog post
     *
     * @param array $formData
     * @param array $blogPost
     * @return bool
     */
    public function deletePost(array $formData, array $blogPost = []): bool
    {
        $this->db->beginTransaction();
        try {
            if (!empty($formData['create_redirect'])) {
                $blogUrl = \implode('/', [
                    'blog',
                    $blogPost['blogyear'],
                    $blogPost['blogmonth'] > 9
                        ? $blogPost['blogmonth']
                        : '0' . $blogPost['blogmonth'],
                    $blogPost['slug']
                ]);
                try {
                    if (\preg_match('#^https?://#', $formData['redirect_url'])) {
                        $cabin = $this->getCabinNameFromURL($formData['redirect_url']);
                    } else {
                        $cabin = $this->cabin;
                    }
                    $this->db->insert(
                        'airship_custom_redirect',
                        [
                            'oldpath' => $blogUrl,
                            'newpath' => $formData['redirect_url'],
                            'cabin' => $cabin,
                            'same_cabin' => $cabin === $this->cabin
                        ]
                    );
                } catch (CabinNotFound $ex) {
                    $this->db->insert(
                        'airship_custom_redirect',
                        [
                            'oldpath' => $blogUrl,
                            'newpath' => $formData['redirect_url'],
                            'cabin' => $this->cabin,
                            'same_cabin' => false
                        ]
                    );
                }
            }
            $this->db->delete(
                'hull_blog_post_versions',
                [
                    'post' => $blogPost['postid']
                ]
            );
            $this->db->delete(
                'hull_blog_post_tags',
                [
                    'postid' => $blogPost['postid']
                ]
            );
            $this->db->delete(
                'hull_blog_comments',
                [
                    'blogpost' => $blogPost['postid']
                ]
            );
            $this->db->delete(
                'hull_blog_posts',
                [
                    'postid' => $blogPost['postid']
                ]
            );
        } catch (DBException $ex) {
            $this->db->rollBack();
            return false;
        }
        \Airship\clear_cache();
        return $this->db->commit();
    }

    /**
     * Permanently remove a blog post series.
     *
     * @param int $seriesId
     * @return bool
     */
    public function deleteSeries(int $seriesId): bool
    {
        $this->db->beginTransaction();

        $this->db->delete(
            'hull_blog_series_items',
            [
                'parent' =>
                    $seriesId
            ]
        );
        $this->db->delete(
            'hull_blog_series_items',
            [
                'series' =>
                    $seriesId
            ]
        );
        $this->db->delete(
            'hull_blog_series',
            [
                'seriesid' =>
                    $seriesId
            ]
        );
        \Airship\clear_cache();
        return $this->db->commit();
    }

    /**
     * Rename a tag
     *
     * @param int $tagId
     * @param array $post
     * @return bool
     */
    public function editTag(int $tagId, array $post): bool
    {
        $this->db->beginTransaction();
        $this->db->update(
            'hull_blog_tags',
            [
                'name' => $post['name']
            ], [
                'tagid' => $tagId
            ]
        );
        return $this->db->commit();
    }

    /**
     * Get all of the series (paginated)
     *
     * @param int $offset
     * @param int $limit
     * @return array
     */
    public function getAllSeries(int $offset, int $limit): array
    {
        $series = $this->db->run(
            \Airship\queryString('blog.series.list_all',
                [
                    'offset' => $offset,
                    'limit' => $limit
                ]
            )
        );
        if (empty($series)) {
            return [];
        }
        return $series;
    }

    /**
     * Get all of the series that this series belongs to, recursively.
     *
     * This is mostly used for generating the appropriate "prev/next"
     * links on the View Blog Post page.
     *
     * @param array $seriesIds
     * @param int $depth
     * @return array
     */
    public function getAllSeriesParents(array $seriesIds, int $depth = 0): array
    {
        if ($depth > 100) {
            return [];
        }
        $ret = [];
        foreach ($seriesIds as $ser) {
            $parents = $this->db->first(
                'SELECT parent FROM hull_blog_series_items WHERE series = ?',
                $ser
            );
            if (!empty($parents)) {
                foreach ($this->getAllSeriesParents($parents, $depth + 1) as $par) {
                    $ret[] = $par;
                }
            }
            $ret []= $ser;
        }
        return $ret;
    }

    /**
     * Get information about a blog post
     *
     * @param int $postId
     * @return array
     */
    public function getBlogPostById(int $postId): array
    {
        $post = $this->db->row(
            'SELECT * FROM view_hull_blog_post WHERE postid = ?',
            $postId
        );
        if (empty($post)) {
            return [];
        }
        return $post;
    }

    /**
     * Get the latest version of a blog post
     *
     * @param int $postId
     * @return array
     */
    public function getBlogPostLatestVersion(int $postId): array
    {
        $post = $this->db->row(
            \Airship\queryString('blog.posts.latest_version'),
            $postId
        );
        if (empty($post)) {
            return [];
        }
        if (!empty($post['metadata'])) {
            $post['metadata'] = \json_decode($post['metadata'], true);
        }
        return $post;
    }

    /**
     * @param int $postId
     * @return array
     */
    public function getBlogPostVersions(int $postId): array
    {
        $posts = $this->db->run(
            \Airship\queryString('blog.posts.list_versions'),
            $postId
        );
        if (empty($posts)) {
            return [];
        }
        return $posts;
    }

    /**
     * Get a specific blog post version, given a unique ID
     *
     * @param string $uniqueID
     * @return array
     */
    public function getBlogPostVersionByUniqueId(string $uniqueID): array
    {
        $post = $this->db->row(
            \Airship\queryString('blog.posts.get_version'),
            $uniqueID
        );
        if (empty($post)) {
            return [];
        }
        if (!empty($post['metadata'])) {
            $post['metadata'] = \json_decode($post['metadata'], true);
        }
        return $post;
    }

    /**
     * Get all of a category's parents
     *
     * @param int $categoryId
     * @param int $depth
     * @return array
     */
    public function getCategoryParents(int $categoryId, int $depth = 0): array
    {
        if ($depth > 100) {
            return [];
        }
        $parent = $this->db->cell(
            'SELECT parent FROM hull_blog_categories WHERE categoryid = ?',
            $categoryId
        );
        if (empty($parent)) {
            return [];
        }
        $recursion = $this->getCategoryParents($parent, $depth + 1);
        \array_unshift($recursion, $parent);
        return $recursion;
    }

    /**
     * Get a full category tree, recursively, from a given parent
     *
     * @param int $parent
     * @param string $col The "children" column name
     * @param array $seen
     * @param int $depth How deep are we?
     *
     * @return array
     */
    public function getCategoryTree(
        $parent = null,
        string $col = 'children',
        array $seen = [],
        int $depth = 0
    ): array {
        if ($parent > 0) {
            $ids = $this->db->escapeValueSet($seen, 'int');
            $rows = $this->db->run(
                "SELECT * FROM hull_blog_categories WHERE categoryid NOT IN {$ids} AND parent = ? ORDER BY name ASC",
                $parent
            );
        } else {
            $rows = $this->db->run(
                "SELECT * FROM hull_blog_categories WHERE parent IS NULL OR parent = '0' ORDER BY name ASC"
            );
        }
        if (empty($rows)) {
            return [];
        }
        foreach ($rows as $i => $row) {
            $_seen = $seen;
            $rows[$i]['ancestors'] = $seen;
            $_seen[] = $row['categoryid'];
            $rows[$i][$col] = $this->getCategoryTree(
                $row['categoryid'],
                $col,
                $_seen,
                $depth + 1
            );
        }
        return $rows;
    }

    /**
     * Get a category
     *
     * @param int $categoryId
     * @return array
     */
    public function getCategoryInfo(int $categoryId = 0): array
    {
        $row = $this->db->row(
            'SELECT * FROM hull_blog_categories WHERE categoryid = ?',
            $categoryId
        );
        if (empty($row)) {
            return [];
        }
        return $row;
    }

    /**
     * Get a blog comment
     *
     * @param int $commentId
     * @param bool $includeReplyTo Also grab the parent comment?
     * @return array
     */
    public function getCommentById(int $commentId, bool $includeReplyTo = true): array
    {
        $comment = $this->db->row(
            'SELECT * FROM hull_blog_comments WHERE commentid = ?',
            $commentId
        );
        if (empty($comment)) {
            return [];
        }
        $comment['body'] = $this->db->cell(
            'SELECT message FROM hull_blog_comment_versions WHERE comment = ? ORDER BY versionid DESC LIMIT 1',
            $commentId
        );
        if (!empty($comment['author'])) {
            $comment['authorname'] = $this->db->cell(
                'SELECT name FROM hull_blog_authors WHERE authorid = ?',
                $comment['author']
            );
        }
        if (!empty($comment['metadata'])) {
            $comment['metadata'] = \json_decode($comment['metadata'], true);
        }
        if ($includeReplyTo) {
            if (!empty($comment['replyto'])) {
                $comment['parent'] = $this->getCommentById((int) $comment['replyto'], false);
                $comment['blog'] = $this->getBlogPostById((int) $comment['blogpost']);
            } else {
                $comment['parent'] = null;
                $comment['blog'] = $this->getBlogPostById((int) $comment['blogpost']);
            }
        }
        return $comment;
    }

    /**
     * Get all of the series for all of the authors the user owns
     *
     * @param int $userId
     * @param int $offset
     * @param int $limit
     * @return array
     */
    public function getSeriesForUser(int $userId, int $offset, int $limit): array
    {
        $authorIds = $this->db->first(
            'SELECT authorid FROM hull_blog_author_owners WHERE userid = ?',
            $userId
        );

        $series = $this->db->run(
            \Airship\queryString('blog.series.list_for_author', [
                'authorids' => $this->db->escapeValueSet($authorIds, 'int'),
                'offset' => $offset,
                'limit' => $limit
            ])
        );
        if (empty($series)) {
            return [];
        }
        return $series;
    }

    /**
     * Get all of the series for a particular author
     *
     * @param int $authorId
     * @param array $exclude Series IDs to exclude
     * @return array
     */
    public function getSeriesForAuthor(int $authorId, array $exclude = []): array
    {
        $series = $this->db->run(
            'SELECT * FROM hull_blog_series WHERE author = ? AND seriesid NOT IN ' .
                $this->db->escapeValueSet($exclude, 'int') .
            ' ORDER BY name ASC',
            $authorId
        );
        if (empty($series)) {
            return [];
        }
        return $series;
    }

    /**
     * Get a single series
     *
     * @param int $seriesId
     *
     * @return array
     */
    public function getSeries(int $seriesId): array
    {
        $series = $this->db->row(
            'SELECT * FROM hull_blog_series WHERE seriesid = ?',
            $seriesId
        );
        if (empty($series)) {
            return [];
        }
        return $series;
    }

    /**
     * Get a single series
     *
     * @param int $seriesId
     *
     * @return array
     */
    public function getSeriesItems(int $seriesId): array
    {
        $items = $this->db->run(
            'SELECT i.* FROM (
                    SELECT
                        item.*,
                        series.name
                    FROM
                        hull_blog_series_items item
                    LEFT JOIN
                        hull_blog_series series ON item.series = series.seriesid
                    WHERE item.parent = ? AND post IS NULL
                UNION
                    SELECT
                        item.*,
                        post.title
                    FROM
                         hull_blog_series_items item
                    LEFT JOIN
                        hull_blog_posts post ON item.post = post.postid
                    WHERE item.parent = ? AND series IS NULL
            ) i ORDER BY i.listorder ASC',
            $seriesId,
            $seriesId
        );
        if (empty($items)) {
            return [];
        }
        return $items;
    }

    /**
     * Get a full series tree, recursively, from a given parent
     *
     * @param int $current
     * @param string $col The "children" column name
     * @param array $encountered Which IDs have we seen before?
     * @param int $depth How deep are we?
     *
     * @return array
     */
    public function getSeriesTree(
        $current = null,
        string $col = 'children',
        array $encountered = [],
        int $depth = 0
    ): array {
        if ($depth > 100) {
            return [];
        }
        $this->db->run(
            \Airship\queryString('blog.series.tree',
                [
                    'valueset' => $this->db->escapeValueSet($encountered, 'int')
                ]
            ),
            $current
        );
        if (empty($rows)) {
            return [];
        }
        foreach ($rows as $i => $row) {
            $rows[$i][$col] = $this->getSeriesTree((int) $row['seriesid'], $col, $depth + 1);
        }
        return $rows;
    }

    /**
     * Get all of the tags in the database
     *
     * @return array
     */
    public function getTags(): array
    {
        $tags =  $this->db->run('SELECT * FROM hull_blog_tags ORDER BY name ASC');
        if (empty($tags)) {
            return [];
        }
        return $tags;
    }

    /**
     * Get a list of all selected blog posts
     *
     * @param int $postId
     * @return array
     */
    public function getTagsForPost(int $postId): array
    {
        return $this->db->first(
            'SELECT tagid FROM hull_blog_post_tags WHERE postid = ?',
            $postId
        );
    }

    /**
     * Get the next version's unique ID
     *
     * @param int $postId
     * @param int $currentVersionId
     * @return string
     */
    public function getNextVersionUniqueId(int $postId, int $currentVersionId): string
    {
        $latest = $this->db->cell(
            'SELECT
                uniqueid
            FROM
                hull_blog_post_versions
            WHERE
                    post = ?
                AND versionid > ?
                ORDER BY versionid DESC
                LIMIT 1
            ',
            $postId,
            $currentVersionId
        );
        if (empty($latest)) {
            return '';
        }
        return $latest;
    }

    /**
     * Get the previous version's unique ID
     *
     * @param int $postId
     * @param int $currentVersionId
     * @return string
     */
    public function getPrevVersionUniqueId(int $postId, int $currentVersionId): string
    {
        $latest = $this->db->cell(
            'SELECT
                uniqueid
            FROM
                hull_blog_post_versions
            WHERE
                    post = ?
                AND versionid < ?
                ORDER BY versionid DESC
                LIMIT 1
            ',
            $postId,
            $currentVersionId
        );
        if (empty($latest)) {
            return '';
        }
        return $latest;
    }

    /**
     * Get data on a specific tag
     *
     * @param int $tagId
     * @return array
     */
    public function getTagInfo(int $tagId): array
    {
        $tagInfo = $this->db->row(
            'SELECT * FROM hull_blog_tags WHERE tagid = ?',
            $tagId
        );
        if (empty($tagInfo)) {
            return [];
        }
        return $tagInfo;
    }

    /**
     * Make a comment invisible on blog posts.
     *
     * @param int $commentId
     * @return bool
     */
    public function hideComment(int $commentId): bool
    {
        $this->db->beginTransaction();
        $this->db->update(
            'hull_blog_comments',
            [
                'approved' => false
            ],
            [
                'commentid' => $commentId
            ]
        );
        $latestVersion = $this->db->cell(
            'SELECT MAX(versionid) FROM hull_blog_comment_versions WHERE comment = ?',
            $commentId
        );
        $this->db->update(
            'hull_blog_comment_versions',
            [
                'approved' => false
            ],
            [
                'versionid' => $latestVersion
            ]
        );
        return $this->db->commit();
    }

    /**
     * List comments
     *
     * @param int $offset
     * @param int $limit
     * @return array
     */
    public function listComments(int $offset = 0, int $limit = 20): array
    {
        $comments = $this->db->run(
            \Airship\queryString('blog.comments.list_all', [
                'offset' => $offset,
                'limit' => $limit
            ])
        );
        $bp = [];
        foreach ($comments as $i => $com) {
            if (!\array_key_exists($com['blogpost'], $bp)) {
                $bp[$com['blogpost']] = $this->getBlogPostById(
                    (int) $com['blogpost']
                );
            }
            $comments[$i]['blog'] = $bp[$com['blogpost']];
        }
        if (empty($comments)) {
            return [];
        }
        return $comments;
    }

    /**
     * Get the most recent posts
     *
     * @param bool $showAll
     * @param int $offset
     * @param int $limit
     * @return array
     */
    public function listPosts(
        bool $showAll = false,
        int $offset = 0,
        int $limit = 20
    ): array {
        if ($showAll) {
            // You're an admin, so you get to see non-public information
            $posts = $this->db->run(
                \Airship\queryString(
                    'blog.posts.list_all',
                    [
                        'offset' => $offset,
                        'limit' => $limit
                    ]
                )
            );
        } else {
            // Only show posts that are public or owned by one of
            // the authors this user belongs to
            $posts = $this->db->safeQuery(
                \Airship\queryString(
                    'blog.posts.list_mine',
                    [
                        'offset' => $offset,
                        'limit' => $limit
                    ]
                ),
                [
                    \Airship\ViewFunctions\userid()
                ]
            );
        }
        // Always return an array
        if (empty($posts)) {
            return [];
        }
        return $posts;
    }

    /**
     * Get all of the series for a particular author
     *
     * @param int $authorId
     * @param array $exclude Series IDs to exclude
     * @return array
     */
    public function listPostsForAuthor(int $authorId, array $exclude = []): array
    {
        $series = $this->db->run(
            'SELECT * FROM hull_blog_posts WHERE author = ? AND postid NOT IN ' .
                $this->db->escapeValueSet($exclude, 'int') .
            ' ORDER BY title ASC',
            $authorId
        );
        if (empty($series)) {
            return [];
        }
        return $series;
    }

    /**
     * List tags (paginated, sortable).
     *
     * @param int $offset
     * @param int $limit
     * @param string $sort
     * @param bool $desc
     * @return array
     */
    public function listTags(
        int $offset,
        int $limit,
        string $sort = 'name',
        bool $desc = false
    ): array {
        $orderBy = $this->orderBy(
            $sort,
            $desc ? 'DESC' : 'ASC',
            ['name', 'created']
        );
        $tags = $this->db->safeQuery(
            \Airship\queryString(
                'blog.tags.list_all',
                [
                    'orderby' => $orderBy,
                    'offset' => $offset,
                    'limit' => $limit
                ]
            )
        );
        if (empty($tags)) {
            return [];
        }
        return $tags;
    }

    /**
     * Get the number of items in a given series.
     *
     * @param int $seriesId
     * @return int
     */
    public function numItemsInSeries(int $seriesId): int
    {
        return (int) $this->db->cell(
            'SELECT count(itemid) FROM hull_blog_series_items WHERE parent = ?',
            $seriesId
        );
    }

    /**
     * Count the number of posts. trinary:
     *    NULL -> all posts
     *    TRUE -> all published posts
     *   FALSE -> all unpublished posts
     *
     * @param mixed $published
     * @return int
     */
    public function numComments($published = null): int
    {
        if ($published === null) {
            return (int) $this->db->cell(
                'SELECT count(commentid) FROM hull_blog_comments'
            );
        }
        if ($published) {
            return (int) $this->db->cell(
                'SELECT count(commentid) FROM hull_blog_comments WHERE approved'
            );
        }
        return (int) $this->db->cell(
            'SELECT count(commentid) FROM hull_blog_comments WHERE NOT approved'
        );
    }

    /**
     * Count the number of posts. trinary:
     *    NULL -> all posts
     *    TRUE -> all published posts
     *   FALSE -> all unpublished posts
     *
     * @param mixed $published
     * @return int
     */
    public function numPosts($published = null): int
    {
        if ($published === null) {
            return (int) $this->db->cell(
                'SELECT count(postid) FROM hull_blog_posts'
            );
        }
        if ($published) {
            return (int) $this->db->cell(
                'SELECT count(postid) FROM hull_blog_posts WHERE status'
            );
        }
        return (int) $this->db->cell(
            'SELECT count(postid) FROM hull_blog_posts WHERE NOT status'
        );
    }

    /**
     * Count the number of series in the database
     *
     * @return int
     */
    public function numSeries(): int
    {
        return (int) $this->db->cell(
            'SELECT count(seriesid) FROM hull_blog_series'
        );
    }

    /**
     * Count the number of series for a user
     *
     * @param int $userId
     * @return int
     * @throws \TypeError
     */
    public function numSeriesForUser(int $userId): int
    {
        $authorIds = $this->db->first(
            'SELECT authorid FROM hull_blog_author_owners WHERE userid = ?',
            $userId
        );
        $authorSet = $this->db->escapeValueSet($authorIds, 'int');
        return (int) $this->db->cell(
            'SELECT count(seriesid) FROM hull_blog_series WHERE author IN ' . $authorSet
        );
    }

    /**
     * Count the number of tags
     *
     * @return int
     */
    public function numTags(): int
    {
        return (int) $this->db->cell(
            'SELECT count(tagid) FROM hull_blog_tags'
        );
    }

    /**
     * Publish an unapproved blog comment.
     *
     * @param int $commentId
     * @return bool
     */
    public function publishComment(int $commentId): bool
    {
        $this->db->beginTransaction();
        $this->db->update(
            'hull_blog_comments',
            [
                'approved' => true
            ],
            [
                'commentid' => $commentId
            ]
        );
        $latestVersion = $this->db->cell(
            'SELECT MAX(versionid) FROM hull_blog_comment_versions WHERE comment = ?',
            $commentId
        );
        $this->db->update(
            'hull_blog_comment_versions',
            [
                'approved' => true
            ],
            [
                'versionid' => $latestVersion
            ]
        );
        return $this->db->commit();
    }

    /**
     * Update a blog category
     *
     * @param int $id
     * @param array $post
     * @return bool
     * @throws \TypeError
     */
    public function updateCategory(int $id, array $post): bool
    {
        $changes = [
            'name' =>
                $post['name'] ?? 'Unnamed',
            'preamble' =>
                $post['preamble'] ?? ''
        ];

        if (!$this->categoryDescendsFrom((int) $post['parent'], $id)) {
            $changes['parent'] = $post['parent'] > 0
                ? $post['parent']
                : null;
        }

        $this->db->beginTransaction();

        if (!empty($post['slug'])) {
            if ($this->updateCategorySlug($id, $post)) {
                $changes['slug'] = $post['slug'];
            }
        }
        $this->db->update(
            'hull_blog_categories',
            $changes,
            [
                'categoryid' => $id
            ]
        );

        return $this->db->commit();
    }

    /**
     * @param int $id
     * @param array $post
     * @return bool
     */
    public function updateCategorySlug(int $id, array $post): bool
    {
        $slug = $this->db->cell('SELECT slug FROM hull_blog_categories WHERE categoryid = ?', $id);
        if ($slug === $post['slug']) {
            // Don't update. It's the same.
            return false;
        }
        if ($this->db->exists('SELECT count(*) FROM hull_blog_categories WHERE slug = ?', $post['slug'])) {
            // Don't update.
            return false;
        }

        if (!empty($post['redirect_slug'])) {
            $oldUrl = \implode('/', [
                'blog',
                'category',
                $slug
            ]);
            $newUrl = \implode('/', [
                'blog',
                'category',
                $post['slug']
            ]);
            $this->db->insert(
                'airship_custom_redirect',
                [
                    'oldpath' => $oldUrl,
                    'newpath' => $newUrl,
                    'cabin' => $this->cabin,
                    'same_cabin' => true
                ]
            );
        }

        // Allow updates to go through.
        return true;
    }

    /**
     * Update a blog post
     *
     * @param array $post
     * @param array $old
     * @param bool $publish
     * @return bool
     */
    public function updatePost(
        array $post,
        array $old,
        bool $publish = false
    ): bool {
        $this->db->beginTransaction();
        $postUpdates = [];

        // First, update the hull_blog_posts entry
        if (!empty($post['author'])) {
            if ($post['author'] !== $old['author']) {
                $postUpdates['author'] = (int) $post['author'];
            }
        }
        if ($post['description'] !== $old['description']) {
            $postUpdates['description'] = (string) $post['description'];
        }
        if ($post['format'] !== $old['format']) {
            $postUpdates['format'] = (string) $post['format'];
        }
        if ($post['slug'] !== $old['slug']) {
            $bm = (string) $old['blogmonth'] < 10
                    ? '0' . $old['blogmonth']
                    : $old['blogmonth'];
            $exists = $this->db->cell(
                'SELECT count(*) FROM view_hull_blog_list WHERE blogmonth = ? AND blogyear = ? AND slug = ?',
                $old['blogyear'],
                $bm,
                $post['slug']
            );
            if ($exists > 0) {
                // Slug collision
                return false;
            }
            $postUpdates['slug'] = (string) $post['slug'];
            if (!empty($post['redirect_slug'])) {
                $oldUrl = \implode('/', [
                    'blog',
                    $old['blogyear'],
                    $bm,
                    $old['slug']
                ]);
                $newUrl = \implode('/', [
                    'blog',
                    $old['blogyear'],
                    $bm,
                    $post['slug']
                ]);
                $this->db->insert(
                    'airship_custom_redirect',
                    [
                        'oldpath' => $oldUrl,
                        'newpath' => $newUrl,
                        'cabin' => $this->cabin,
                        'same_cabin' => true
                    ]
                );
            }
        }
        $now = new \DateTime();
        if (!empty($post['published'])) {
            try {
                $now = new \DateTime($post['published']);
            } catch (\Throwable $ex) {
            }
        }

        if (!\array_key_exists('category', $post)) {
            $post['category'] = 0;
        }
        if ($post['category'] !== $old['category']) {
            $postUpdates['category'] = (int) $post['category'];
        }

        if ($publish) {
            $postUpdates['status'] = true;
            $postUpdates['cache'] = !empty($post['cache']);

            // Let's set the publishing time.
            $postUpdates['published'] = $now->format(\AIRSHIP_DATE_FORMAT);
        }
        if ($post['title'] !== $old['title']) {
            $postUpdates['title'] = (string) $post['title'];
        }
        if (!empty($postUpdates)) {
            $this->db->update(
                'hull_blog_posts',
                $postUpdates,
                [
                    'postid' => $old['postid']
                ]
            );
        }
        do {
            $unique = \Airship\uniqueId();
            $exists = $this->db->exists(
                'SELECT COUNT(*) FROM hull_blog_post_versions WHERE uniqueid = ?',
                $unique
            );
        } while ($exists);

        // Second, create a new entry in hull_blog_post_versions
        $this->db->insert(
            'hull_blog_post_versions',
            [
                'post' =>
                    $old['postid'],
                'body' =>
                    $post['blog_post_body'],
                'format' =>
                    $post['format'],
                'live' =>
                    $publish,
                'metadata' =>
                    \json_encode($post['metadata'] ?? []),
                'published_by' =>
                    $publish
                        ? $this->getActiveUserId()
                        : null,
                'uniqueid' =>
                    $unique
            ]
        );

        if (empty($old['tags'])) {
            $old['tags'] = [];
        }
        if (empty($post['tags'])) {
            $post['tags'] = [];
        }
        // Now let's update the tag relationships
        $tag_ins = \array_diff($post['tags'], $old['tags']);
        $tag_del = \array_diff($old['tags'], $post['tags']);
        foreach ($tag_del as $del) {
            $this->db->delete(
                'hull_blog_post_tags',
                [
                    'postid' => $old['postid'],
                    'tagid' => $del
                ]
            );
        }
        foreach ($tag_ins as $ins) {
            $this->db->insert(
                'hull_blog_post_tags',
                [
                    'postid' => $old['postid'],
                    'tagid' => $ins
                ]
            );
        }
        if ($publish) {
            \Airship\clear_cache();
        }
        return $this->db->commit();
    }

    /**
     * Insert/delete/rearrange the contents of a series.
     *
     * @param int $seriesId
     * @param array $oldItems
     * @param array $post
     * @return bool
     */
    public function updateSeries(
        int $seriesId,
        array $oldItems,
        array $post
    ): bool {
        $this->db->beginTransaction();

        $newItems = \explode(',', $post['items']);
        $inserts = \array_diff($newItems, $oldItems);
        $deletes = \array_diff($oldItems, $newItems);

        foreach ($deletes as $del) {
            list ($type, $itemid) = \explode('_', $del);
            switch ($type) {
                case 'series':
                    $this->db->delete(
                        'hull_blog_series_items',
                        [
                            'parent' => $seriesId,
                            'series' => $itemid
                        ]
                    );
                    break;
                case 'blogpost':
                    $this->db->delete(
                        'hull_blog_series_items',
                        [
                            'parent' => $seriesId,
                            'post' => $itemid
                        ]
                    );
                    break;
            }
        }
        $updates = [
            'name' =>
                $post['name'],
            'preamble' =>
                $post['preamble'] ?? '',
            'format' =>
                $post['format'] ?? 'HTML',
            'config' =>
                $post['config']
                    ? \json_encode($post['config'])
                    : '[]'
        ];
        if (!empty($post['author'])) {
            $updates['author'] = $post['author'];
        }

        $this->db->update(
            'hull_blog_series',
            $updates,
            [
                'seriesid' => $seriesId
            ]
        );

        $listOrder = 0;
        foreach ($newItems as $new) {
            if (\strpos($new, '_') === false) {
                continue;
            }
            list ($type, $itemId) = \explode('_', $new);
            if (\in_array($new, $inserts)) {
                switch ($type) {
                    case 'series':
                        $this->db->insert(
                            'hull_blog_series_items',
                            [
                                'parent' => $seriesId,
                                'series' => $itemId,
                                'listorder' => ++$listOrder
                            ]
                        );
                        break;
                    case 'blogpost':
                        $this->db->insert(
                            'hull_blog_series_items',
                            [
                                'parent' => $seriesId,
                                'post' => $itemId,
                                'listorder' => ++$listOrder
                            ]
                        );
                        break;
                }
            } else {
                switch ($type) {
                    case 'series':
                        $this->db->update(
                            'hull_blog_series_items',
                            [
                                'listorder' => ++$listOrder
                            ],
                            [
                                'parent' => $seriesId,
                                'series' => $itemId
                            ]
                        );
                        break;
                    case 'blogpost':
                        $this->db->update(
                            'hull_blog_series_items',
                            [
                                'listorder' => ++$listOrder
                            ],
                            [
                                'parent' => $seriesId,
                                'post' => $itemId
                            ]
                        );
                        break;
                }
            }
        }

        return $this->db->commit();
    }

    #=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#

    /**
     * Make a generic slug for most tables
     *
     * @param string $title What are we basing the URL off of?
     * @param string $year
     * @param string $month
     * @return string
     */
    protected function makeBlogPostSlug(
        string $title,
        string $year = '',
        string $month = ''
    ): string {
        if (empty($year)) {
            $year = \date('Y');
        }
        if (empty($month)) {
            $month = \date('m');
        }
        $query = 'SELECT count(*) FROM view_hull_blog_list WHERE blogmonth = ? AND blogyear = ? AND slug = ?';
        $slug = $base_slug = \Airship\slugFromTitle($title);
        $i = 1;
        while ($this->db->exists($query, $month, $year, $slug)) {
            $slug = $base_slug . '-' . ++$i;
        }
        return $slug;
    }
}
