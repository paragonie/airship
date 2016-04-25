<?php
declare(strict_types=1);
namespace Airship\Cabin\Hull\Blueprint;

use Airship\Alerts\Router\EmulatePageNotFound;

require_once __DIR__.'/gear.php';

/**
 * Class Blog
 *
 * Read-only access to blog posts, etc.
 * Create comments.
 *
 * @package Airship\Cabin\Hull\Blueprint
 */
class Blog extends BlueprintGear
{
    /**
     * Adds a new comment to a blog post
     *
     * @param array $post
     * @param int $blogPostId
     * @param bool $published
     * @return bool
     */
    public function addCommentToPost(array $post, int $blogPostId, bool $published = false): bool
    {
        $replyTo = isset($post['reply_to'])
            ? $this->checkCommentReplyTo(
                (int) $post['reply_to'],
                $blogPostId
            )
            : null;

        if (!empty($post['author'])) {
            $metadata = null;
        } else {
            $metadata = \json_encode([
                'name' => $post['name'],
                'email' => $post['email'],
                'url' => $post['url']
            ]);
        }
        // We're going to do this inside of a transaction:
        $this->db->beginTransaction();

        // Create the new comment:
        $commentId = $this->db->insertGet(
            'hull_blog_comments',
            [
                'blogpost' => $blogPostId,
                'replyto' => $replyTo,
                'approved' => $published ?? false,
                'metadata' => $metadata
            ],
            'commentid'
        );

        if (!empty($commentId)) {
            // Insert the comment
            $this->db->insert(
                'hull_blog_comment_versions', [
                    'comment' => $commentId,
                    'approved' => $published ?? false,
                    'message' => $post['message']
                ]
            );
            // Hooray!
            return $this->db->commit();
        }
        $this->db->rollBack();
        return false;
    }

    /**
     * Make sure we aren't replying to a comment on a different blog post
     *
     * @param int $replyTo
     * @param int $blogPostId
     * @return mixed
     */
    public function checkCommentReplyTo(int $replyTo, int $blogPostId)
    {
        $parent = $this->db->cell(
            'SELECT blogpost FROM hull_blog_comments WHERE commentid = ?',
            $replyTo
        );
        if (empty($parent)) {
            return null;
        }
        if ($parent === $blogPostId) {
            return $replyTo;
        }
        return null;
    }

    /**
     * Return an array of the most $num recent posts, including URL and title
     *
     * @param int $authorId
     * @return int
     */
    public function countByAuthor(int $authorId): int
    {
        return $this->db->cell(
            'SELECT
                count(postid)
            FROM
                view_hull_blog_list
            WHERE
                status
                AND author = ?',
            $authorId
        );
    }

    /**
     * Return an array of the most $num recent posts, including URL and title
     *
     * @param mixed $categories
     * @return int
     */
    public function countByCategories(array $categories = []): int
    {
        if (empty($categories)) {
            return $this->db->cell(
                'SELECT
                    count(postid)
                FROM
                    view_hull_blog_list
                WHERE
                    status
                    AND categoryid IS NULL'
            );
        }
        $imp = $this->db->escapeValueSet($categories, 'int');
        return $this->db->cell(
            'SELECT
                count(postid)
            FROM
                view_hull_blog_list
            WHERE
                status
                AND categoryid IN ' . $imp
        );
    }

    /**
     * Return an array of the most $num recent posts, including URL and title
     *
     * @param string $year
     * @param string $month
     * @return int
     */
    public function countByMonth(string $year, string $month): int
    {
        return $this->db->cell(
            'SELECT
                count(postid)
            FROM
                view_hull_blog_list
            WHERE
                status
                AND blogyear = ?
                AND blogmonth = ?',
            $year,
            $month
        );
    }

    /**
     * Return an array of the most $num recent posts, including URL and title
     *
     * @param string $year
     * @return int
     */
    public function countByYear(string $year): int
    {
        return $this->db->cell(
            'SELECT
                count(postid)
            FROM
                view_hull_blog_list
            WHERE
                status
                AND blogyear = ?',
            $year
        );
    }
    /**
     * How many items are in this series?
     *
     * @return int
     */
    public function countSeries(): int
    {
        return (int) $this->db->cell(
            'SELECT
                count(*)
            FROM
                hull_blog_series_items
            WHERE
                parent IS NULL
                AND series IS NOT NULL'
        );
    }

    /**
     * How many items are in this series?
     *
     * @param int $seriesId
     * @return int
     */
    public function countBySeries(int $seriesId): int
    {
        $sub_series = (int) $this->db->cell(
            'SELECT
                count(*)
            FROM
                hull_blog_series_items
            WHERE
                parent = ?
                AND series IS NOT NULL',
            $seriesId
        );

        $posts = (int) $this->db->cell(
            'SELECT
                count(i.*)
            FROM
                hull_blog_series_items i
            LEFT JOIN
                hull_blog_posts p
                ON i.post = p.postid
            WHERE
                i.parent = ?
                AND p.status
                AND i.post IS NOT NULL',
            $seriesId
        );

        return $sub_series + $posts;
    }

    /**
     * Return an array of the most $num recent posts, including URL and title
     *
     * @param int $tag
     * @return int
     */
    public function countByTag(int $tag): int
    {
        return $this->db->cell(
            'SELECT
                count(p.postid)
            FROM
                hull_blog_posts p
            LEFT JOIN
              hull_blog_post_tags t
              ON t.postid = p.postid
            WHERE
                p.status
                AND t.tagid = ?',
            $tag
        );
    }

    /**
     * Expand a category to include all subcategories
     *
     * @param int $category_id
     * @param int $depth
     * @return int[]
     */
    public function expandCategory(int $category_id = 0, int $depth = 0)
    {
        $flat = [];
        if ($depth === 0 && $category_id > 0) {
            $flat[] = $category_id;
        }
        $cats = $this->db->run(
            'SELECT
                categoryid
            FROM
                hull_blog_categories
            WHERE
                parent = ?
            ',
            $category_id
        );
        if (empty($cats)) {
            return [];
        }
        foreach ($cats as $cat) {
            $kids = $this->expandCategory(
                (int) $cat['categoryid'],
                $depth + 1
            );
            $flat[] = $cat['categoryid'];
            if (!empty($kids)) {
                foreach($kids as $kid) {
                    if ($kid > 0) {
                        $flat[] = $kid;
                    }
                }
            }
        }
        return $flat;
    }

    /**
     * Return information about the current category
     *
     * @param string $authorSlug
     * @return array
     */
    public function getAuthorBySlug(string $authorSlug): array
    {
        $author = $this->db->row(
            'SELECT
                *
            FROM
                hull_blog_authors
            WHERE
                slug = ?',
            $authorSlug
        );
        if (empty($author)) {
            return [];
        }
        return $author;
    }

    /**
     * Return information about the current category
     *
     * @param int $authorId
     * @return array
     */
    public function getAuthor(int $authorId): array
    {
        $author = $this->db->row(
            'SELECT
                *
            FROM
                hull_blog_authors
            WHERE
                authorid = ?',
            $authorId
        );
        if (empty($author)) {
            return [];
        }
        return $author;
    }

    /**
     * Get all of the authors available for this user to post under
     *
     * @param int $userId
     * @return array
     * @throws EmulatePageNotFound
     */
    public function getAuthorsForUser(int $userId): array
    {
        $authors = $this->db->first(
            'SELECT authorid FROM view_hull_users_authors WHERE userid = ?',
            $userId
        );
        if (empty($authors)) {
            return [];
        }
        return $authors;
    }

    /**
     * Get a specific blog post
     *
     * @param string $year
     * @param string $month
     * @param string $slug
     * @return array
     * @throws EmulatePageNotFound
     */
    public function getBlogPost(string $year, string $month, string $slug): array
    {
        $post = $this->db->row(
            'SELECT
                *
            FROM
                view_hull_blog_post
            WHERE
                status
                AND blogyear = ?
                AND blogmonth = ?
                AND slug = ?
            ',
            $year,
            $month,
            $slug
        );
        if (empty($post)) {
            throw new EmulatePageNotFound();
        }
        return $this->getSnippet($this->postProcess($post), true);
    }

    /**
     * Get a specific blog post by its row ID
     *
     * @param int $id
     * @return array
     * @throws EmulatePageNotFound
     */
    public function getBlogPostById(int $id): array
    {
        $post = $this->db->row(
            'SELECT
                *
            FROM
                view_hull_blog_list
            WHERE
                status
                AND postid = ?
            ',
            $id
        );
        if (empty($post)) {
            throw new EmulatePageNotFound();
        }
        return $this->getSnippet($this->postProcess($post), true);
    }

    /**
     * Return information about the current category
     *
     * @param string $slug
     * @return array
     * @throws EmulatePageNotFound
     */
    public function getCategory(string $slug): array
    {
        $cat = $this->db->row(
            'SELECT
                *
            FROM
                hull_blog_categories
            WHERE
                slug = ?',
            $slug
        );
        if (empty($cat)) {
            throw new EmulatePageNotFound();
        }
        return $cat;
    }

    /**
     * Return information about the current tag
     *
     * @param string $slug
     * @return array
     * @throws EmulatePageNotFound
     */
    public function getTag(string $slug): array
    {
        $tag = $this->db->row(
            'SELECT
                *
            FROM
                hull_blog_tags
            WHERE
                slug = ?',
            $slug
        );
        if (empty($tag)) {
            throw new EmulatePageNotFound();
        }
        return $tag;
    }

    /**
     * Gets the data necessary for the side menu on blog pages
     *
     * @return array
     */
    public function getBlogMenu(): array
    {
        $years = [];
        $getYears = $this->db->run(
            "SELECT DISTINCT
                date_part('year', published) AS blogyear,
                date_part('month', published) AS blogmonth
            FROM hull_blog_posts
            WHERE status = 't'
            GROUP BY
                date_part('year', published),
                date_part('month', published)
            ORDER BY
                blogyear DESC,
                blogmonth ASC
            "
        );
        if (!empty($getYears)) {
            foreach ($getYears as $yr) {
                $dt = new \DateTime($yr['blogyear'] . '-' . $yr['blogmonth'] . '-01');
                $y = intval($yr['blogyear']);
                if ($yr['blogmonth'] < 10) {
                    // Left-pad with a single zero
                    $yr['blogmonth'] = '0' . $yr['blogmonth'];
                }
                $years[$y][$yr['blogmonth']] = $dt->format('F');
            }
        }

        return [
            'years' => $years,
            'categories' => $this->getCategoryTree(),
            'tags' => $this->getTagCloud()
        ];
    }

    /**
     * Get all subcategories for a given category (defaults to root)
     *
     * @param int $parent
     * @return array
     */
    public function getCategoryTree(int $parent = 0): array
    {
        if ($parent === 0) {
            $cats = $this->db->run(
                'SELECT
                    categoryid,
                    name,
                    slug
                FROM
                    hull_blog_categories
                WHERE
                    parent IS NULL
                ORDER BY name ASC
                '
            );
        } else {
            $cats = $this->db->run(
                'SELECT
                    categoryid,
                    name,
                    slug
                FROM
                    hull_blog_categories
                WHERE
                    parent = ?
                ORDER BY name ASC
                ',
                $parent
            );
        }
        if (empty($cats)) {
            return [];
        }
        foreach ($cats as $i => $cat) {
            $cats[$i]['children'] = $this->getCategoryTree(
                (int) $cat['categoryid']
            );
        }
        return $cats;
    }

    /**
     * Get all of the blog comments for a given blog post
     *
     * @param int $blogPostId
     * @return array
     */
    public function getCommentTree(int $blogPostId): array
    {
        $tree = [];
        $comments = $this->db->run('
            SELECT
              commentid
            FROM
              hull_blog_comments
            WHERE
                blogpost = ?
                AND replyto IS NULL',
            $blogPostId
        );
        foreach ($comments as $com) {
            $data = $this->getCommentWithChildren($com['commentid']);
            if (!empty($data)) {
                $tree[] = $data;
            }
        }
        return $tree;
    }

    /**
     * Get a comment (and all of its replies)
     *
     * @param int $commentId
     * @return array
     */
    public function getCommentWithChildren(int $commentId): array
    {
        $versionId = $this->db->cell('
            SELECT
              MAX(versionid)
            FROM
              hull_blog_comment_versions
            WHERE
              approved
              AND comment = ?
        ', $commentId);
        if (empty($versionId)) {
            return [];
        }

        /**
         * Now let's get the actual comment
         */
        $comment = $this->db->row(
            'SELECT
                 c.*,
                 v.created AS modified,
                 v.message
             FROM
                 view_hull_blog_comments c
             LEFT JOIN
                 hull_blog_comment_versions v
                 ON v.comment = c.commentid
             WHERE
                 v.versionid = ?
             ',
            $versionId
        );
        if (isset($comment['metadata'])) {
            $comment['metadata'] = \json_decode($comment['metadata'], true);
        }

        /**
         * Let's recursively add all of its children:
         */
        $comment['children'] = [];
        $children = $this->db->run('
            SELECT
              commentid
            FROM
              hull_blog_comments
            WHERE
                replyto = ?',
            $commentId
        );
        foreach ($children as $child) {
            $data = $this->getCommentWithChildren($child['commentid']);
            if (!empty($data)) {
                $comment['children'][] = $data;
            }
        }
        /**
         * Now return:
         */
        return $comment;
    }

    /**
     * Get all of the tags for a particular post
     *
     * @param int $postId
     * @return array
     */
    public function getPostTags(int $postId): array
    {
        return $this->db->run(
            'SELECT
                t.tagid,
                t.slug,
                t.name
            FROM
                hull_blog_post_tags j
            JOIN hull_blog_tags t
                ON j.tagid = t.tagid
            WHERE j.postid = ?
            ORDER BY t.name ASC
            ',
            $postId
        );
    }

    /**
     * Get all of the series' that this post belongs to:
     *
     * @param int $postId
     * @return array
     */
    public function getPostsSeries(int $postId): array
    {
        $series = [];
        foreach ($this->getSeriesForPost($postId) as $ser) {
            if (!empty($ser)) {
                $series []= $ser;
                foreach ($this->getSeriesRecursive($ser['seriesid'], [$ser['seriesid']]) as $par) {
                    if (!empty($par)) {
                        $series [] = $par;
                    }
                }
            }
        }
        foreach ($series as $i => $ser) {
            $series[$i] = $this->getSeriesExtra($ser);
        }
        return $series;
    }

    /**
     * Get extra data
     *
     * @param array $ser
     * @return array
     */
    protected function getSeriesExtra(array $ser): array
    {
        $ser['prev_link'] = $this->getSeriesLink(
            $ser['seriesid'],
            $ser['listorder'],
            'prev'
        );
        $ser['next_link'] = $this->getSeriesLink(
            $ser['seriesid'],
            $ser['listorder'],
            'next'
        );
        $ser['config'] = \json_decode($ser['config'], true);
        return $ser;
    }

    /**
     * Get information about a series
     *
     * @param int $id
     * @return array
     */
    public function getSeriesById(int $id): array
    {
        return $this->db->row(
            'SELECT * FROM hull_blog_series WHERE seriesid = ?',
            $id
        );
    }

    /**
     * Get information about a series
     *
     * @param string $slug
     * @return array
     * @throws EmulatePageNotFound
     */
    public function getSeriesInfo(string $slug): array
    {
        $series = $this->db->row(
            'SELECT * FROM hull_blog_series WHERE slug = ?',
            $slug
        );
        if (empty($series)) {
            throw new EmulatePageNotFound();
        }
        return $series;
    }

    /**
     * @param int $series
     * @param int $listOrder
     * @param string $which
     * @return string
     */
    protected function getSeriesLink(
        int $series,
        int $listOrder,
        string $which = 'curr'
    ): string {
        $query = "
            SELECT
                *
            FROM
                hull_blog_series_items
            WHERE
                parent = ?
            AND listorder";

        // Dynamic query:
        switch ($which) {
            case 'prev':
                $query .= ' < ? ORDER BY listorder DESC';
                break;
            case 'curr':
                $query .= ' = ?';
                break;
            case 'next':
                $query .= ' > ? ORDER BY listorder ASC';
                break;
            default:
                $query .= ' != ? ORDER BY listorder ASC';
        }

        $data = $this->db->row($query, $series, $listOrder);
        if (empty($data)) {
            return '';
        }

        if (!empty($data['series'])) {
            return '/blog/series/' . $this->db->cell(
                'SELECT slug FROM hull_blog_series WHERE seriesid = ?',
                $data['series']
            );
        }
        if (!empty($data['post'])) {
            $b = $this->db->row(
                'SELECT blogyear, blogmonth, slug FROM view_hull_blog_list WHERE postid = ?',
                $data['post']
            );
            return '/blog/' . $b['blogyear'] . '/' . \str_pad($b['blogmonth'], 2, '0', STR_PAD_LEFT) . '/' . $b['slug'];
        }
        return '';
    }

    /**
     * Recursively acquire parent series IDs into a flat array
     *
     * @param int $seriesId
     * @param array $seen
     * @param int $depth
     * @return array
     */
    protected function getSeriesRecursive(
        int $seriesId,
        array $seen = [],
        int $depth = 0
    ): array {
        $series = [];
        foreach ($this->getParentSeries($seriesId, $seen, $depth) as $ser) {
            if (!empty($ser)) {
                $series [] = $ser;

                $_seen = $seen;
                \array_push($_seen, $ser['seriesid']);

                $parents = $this->getSeriesRecursive(
                    $ser['seriesid'],
                    $seen,
                    $depth + 1
                );
                foreach ($parents as $par) {
                    if (!empty($par)) {
                        $series [] = $par;
                    }
                }
            }
        }
        return $series;
    }

    /**
     * Get all the series that a given post belongs to.
     *
     * @param int $postId
     * @return array
     */
    public function getSeriesForPost(int $postId): array
    {
        return $this->db->run("
            SELECT
                s.*,
                i.listorder
            FROM
                hull_blog_series s
            LEFT JOIN
                hull_blog_series_items i
                ON i.parent = s.seriesid
            WHERE i.post = ?
            ",
            $postId
        );
    }

    /**
     * Get all of the parent series that a series belongs to.
     *
     * @param int $seriesId
     * @param array $seenIds
     * @param int $depth
     * @return array
     */
    public function getParentSeries(
        int $seriesId,
        array $seenIds = [],
        int $depth = 0
    ): array {
        $addendum = '';
        if (!empty($seenIds)) {
            $addendum = "AND i.series NOT IN " . $this->db->escapeValueSet($seenIds, 'int');
        }
        return $this->db->run("
            SELECT
                s.*,
                i.listorder,
                '".$depth."' as depth
            FROM
                hull_blog_series s
            LEFT JOIN
                hull_blog_series_items i
                ON i.parent = s.seriesid
            WHERE i.series = ?
            " . $addendum,
            $seriesId
        );
    }

    /**
     * Get a preview snippet of a blog post
     *
     * @param array $post Post data
     * @param boolean $after Do we want the content after the fold?
     * @return array
     */
    public function getSnippet(array $post, bool $after = false): array
    {
        // First, let's try cutting it off by section...
        if (empty($post['body'])) {
            return $post;
        }
        $i = 0;
        if ($post['format'] === 'Rich Text') {
            $post['body'] = \str_replace(
                '><',
                '>' . "\n" . '<',
                $post['body']
            );
        }
        $lines = \explode("\n", $post['body']);
        if (count($lines) < 4 || \strlen($post['body']) < 200) {
            $post['snippet'] = $post['body'];
            $post['after_fold'] = '';
            return $post;
        }
        $cutoff = null;
        foreach ($lines as $i => $line) {
            if (empty($line)) {
                continue;
            }
            if ($post['format'] === 'RST') {
                if (\preg_match('#^(['.\preg_quote('!"#$%&\'()*+,-./:;<=>?@[\]^_`{|}~', '#').'])#', $line[0], $m)) {
                    if ($i > 2 && \trim($line) === \str_repeat($m[1], \strlen($line))) {
                        $cutoff = $i;
                        break;
                    }
                }
            } elseif ($post['format'] === 'Markdown') {
                if (\preg_match('#^(['.\preg_quote('#', '#').']{1,})#', $line[0], $m)) {
                    $cutoff = $i;
                    break;
                }
            } elseif ($post['format'] === 'HTML' || $post['format'] === 'Rich Text') {
                if (\preg_match('#^<'.'h[1-7]>#', $line[0], $m)) {
                    $cutoff = $i;
                    break;
                }
            }
        }
        if ($cutoff !== null) {
            $post['snippet'] = \implode(
                "\n",
                \array_slice($lines, 0, $i - 1)
            );
            if ($after) {
                $post['after_fold'] = \implode(
                    "\n",
                    \array_slice($lines, $i - 1)
                );
            }
            return $post;
        }

        // Next, let's find the 37% mark for breaks

        $split = ($post['format'] === 'Rich Text' || $post['format'] === 'HTML')
            ? "\n"
            : "\n\n";
        $sects = \explode($split, $post['body']);
        $cut = (int) \ceil(0.37 * \count($sects));
        if ($sects < 2) {
            $post['snippet'] = $post['body'];
            $post['after_fold'] = '';
            return $post;
        }
        if (\preg_match('#^\.\. #', $sects[ $cut - 1 ])) {
            --$cut;
        }
        $post['snippet'] = \implode(
            "\n\n",
            \array_slice($sects, 0, $cut - 1)
        )."\n";
        if ($after) {
            $post['after_fold'] = "\n".\implode($split, \array_slice($sects, $cut - 1));
        }
        return $post;
    }

    /**
     * Get $num of the most popular tags, paginated to start at $offset
     *
     * @param int $num
     * @param int $offset
     * @return array
     */
    public function getTags(int $num = 10, int $offset = 0)
    {
        return $this->db->run(
            'SELECT
                t.tagid,
                t.name,
                t.slug,
                COUNT(j.postid) AS num_posts
            FROM
                hull_blog_tags t
            LEFT JOIN
                hull_blog_post_tags j
                ON j.tagid = t.tagid
            JOIN
                hull_blog_posts p
                ON j.postid = p.postid
            WHERE
                p.status
            GROUP BY
                t.tagid,
                t.name,
                t.slug
            ORDER BY COUNT(j.postid) DESC, t.name ASC
            OFFSET '.$offset.'
            LIMIT '.$num
        );
    }

    /**
     * Get $num of the most popular tags, paginated to start at $offset
     *
     * @return array
     */
    public function getTagCloud(): array
    {
        $tags = $this->db->run(
            'SELECT
                t.tagid,
                t.name,
                t.slug,
                COUNT(j.postid) AS num_posts
            FROM
                hull_blog_tags t
            LEFT JOIN
                hull_blog_post_tags j
                ON j.tagid = t.tagid
            JOIN hull_blog_posts p
                ON j.postid = p.postid
            WHERE
                p.status AND p.published <= current_timestamp
            GROUP BY
                t.tagid,
                t.name,
                t.slug
            ORDER BY t.name ASC'
        );

        if (empty($tags)) {
            return [];
        }

        $avg = \array_sum(\array_column($tags, 'num_posts')) / \count($tags);
        if ($avg == 0) {
            $avg = 1;
        }

        foreach ($tags as $i => $tag) {
            $tags[$i]['post_ratio'] = \round(
                $tag['num_posts'] / $avg,
                2
            );
        }
        return $tags;
    }

    /**
     * Return all blog posts
     * @return array
     */
    public function listAllPublic(): array
    {
        $posts = $this->db->run(
            'SELECT
                *
            FROM
                view_hull_blog_post
            WHERE
                status
            ORDER BY published DESC
            '
        );

        if (empty($posts)) {
            return [];
        }

        foreach ($posts as $i => $post) {
            $posts[$i] = $this->postProcess($post);
        }

        return $posts;
    }

    /**
     * Return an array of the most $num recent posts, including URL and title
     *
     * @param int $authorId
     * @param int $num
     * @param int $offset
     * @return array
     */
    public function listByAuthor(int $authorId, int $num = 20, int $offset = 0): array
    {
        $posts = $this->db->run(
            'SELECT
                *
            FROM
                view_hull_blog_post
            WHERE
                status
                AND author = ?
            ORDER BY published DESC
            OFFSET '.$offset.'
            LIMIT '.$num,
            $authorId
        );

        if (empty($posts)) {
            return [];
        }

        foreach ($posts as $i => $post) {
            $posts[$i] = $this->postProcess($post);
        }

        return $posts;
    }

    /**
     * Return an array of the most $num recent posts, including URL and title
     *
     * @param array $categories
     * @param int $num
     * @param int $offset
     * @return array
     */
    public function listByCategories(array $categories = [], int $num = 20, int $offset = 0): array
    {
        if (empty($categories)) {
            $posts = $this->db->run(
                'SELECT
                    *
                FROM
                    view_hull_blog_post
                WHERE
                    status
                    AND categoryid IS NULL
                ORDER BY published DESC
                OFFSET ' . $offset . '
                LIMIT ' . $num
            );
        } else {
            \array_walk($categories, 'intval');
            $imp = \implode(', ', $categories);
            $posts = $this->db->run(
                'SELECT
                    *
                FROM
                    view_hull_blog_post
                WHERE
                    status
                    AND categoryid IN (' . $imp . ')
                ORDER BY published DESC
                OFFSET ' . $offset . '
                LIMIT ' . $num
            );
        }

        if (empty($posts)) {
            return [];
        }

        foreach ($posts as $i => $post) {
            $posts[$i] = $this->postProcess($post);
        }

        return $posts;
    }

    /**
     * Get all of the base series
     *
     * @param int $num
     * @param int $offset
     * @return array
     */
    public function listBaseSeries(int $num = 20, int $offset = 0): array
    {
        $items = $this->db->run(
            'SELECT
                s.*,
                COALESCE(i.listorder, s.seriesid) AS listorder
            FROM
                hull_blog_series s
            LEFT OUTER JOIN
                hull_blog_series_items i
                ON
                    i.series = s.seriesid
            WHERE
                i.parent IS NULL
                AND s.seriesid IS NOT NULL
            ORDER BY
                listorder ASC
            OFFSET '.$offset.'
            LIMIT '.$num
        );
        return $items;
    }

    /**
     * Return an array of the most $num recent posts, including URL and title
     *
     * @param int $seriesId
     * @param int $num
     * @param int $offset
     * @return array
     */
    public function listBySeries(
        int $seriesId,
        int $num = 20,
        int $offset = 0
    ): array {
        $items = $this->db->run(
            'SELECT
                *
            FROM
                hull_blog_series_items
            WHERE
                parent = ?
            ORDER BY
                listorder ASC
            OFFSET '.$offset.'
            LIMIT '.$num,
            $seriesId
        );
        $series_items = [];

        foreach ($items as $item) {
            if ($item['post']) {
                $row = $this->getBlogPostById($item['post']);
                if (!empty($row)) {
                    $row['type'] = 'blogpost';
                    $series_items [] = $row;
                }
            } elseif ($item['series']) {
                $row = $this->getSeriesById($item['series']);
                if (!empty($row)) {
                    $row['type'] = 'series';
                    $series_items [] = $row;
                }
            }
        }
        return $series_items;
    }

    /**
     * Return an array of the most $num recent posts, including URL and title
     *
     * @param int $tagId
     * @param int $num
     * @param int $offset
     * @return array
     */
    public function listByTag(
        int $tagId,
        int $num = 20,
        int $offset = 0
    ): array {
        $posts = $this->db->run(
            'SELECT
                p.*
            FROM
                view_hull_blog_post p
            LEFT JOIN
                hull_blog_post_tags t
                ON p.postid = t.postid
            WHERE
                p.status
                AND t.tagid = ?
            ORDER BY published DESC
            OFFSET '.$offset.'
            LIMIT '.$num,
            $tagId
        );

        if (empty($posts)) {
            return [];
        }

        foreach ($posts as $i => $post) {
            $posts[$i] = $this->postProcess($post);
        }

        return $posts;
    }

    /**
     * Return an array of the most $num recent posts, including URL and title
     *
     * @param string $year
     * @param int $num
     * @param int $offset
     * @return array
     */
    public function listByYear(
        string $year,
        int $num = 20,
        int $offset = 0
    ): array {
        $posts = $this->db->run(
            'SELECT
                *
            FROM
                view_hull_blog_post
            WHERE
                status
                AND blogyear = ?
            ORDER BY published DESC
            OFFSET '.$offset.'
            LIMIT '.$num,
            $year
        );

        if (empty($posts)) {
            return [];
        }

        foreach ($posts as $i => $post) {
            $posts[$i] = $this->postProcess($post);
        }

        return $posts;
    }

    /**
     * Return an array of the most $num recent posts, including URL and title
     *
     * @param string $year
     * @param string $month
     * @param int $num
     * @param int $offset
     * @return array
     */
    public function listByMonth(
        string $year,
        string $month,
        int $num = 20,
        int $offset = 0
    ): array {
        $posts = $this->db->run(
            'SELECT
                *
            FROM
                view_hull_blog_post
            WHERE
                status
                AND blogyear = ?
                AND blogmonth = ?
            ORDER BY published DESC
            OFFSET '.$offset.'
            LIMIT '.$num,
            $year,
            $month
        );

        if (empty($posts)) {
            return [];
        }

        foreach ($posts as $i => $post) {
            $posts[$i] = $this->postProcess($post);
        }

        return $posts;
    }

    /**
     * Return an array of the most $num recent posts, including URL and title
     *
     * @param int $num
     * @param int $offset
     * @return array
     */
    public function recentFullPosts(int $num = 20, int $offset = 0): array
    {
        $posts = $this->db->run(
            'SELECT
                *
            FROM
                view_hull_blog_post
            WHERE
                status
            ORDER BY published DESC
            OFFSET '.(int) $offset.'
            LIMIT '.(int) $num
        );

        if (empty($posts)) {
            return [];
        }

        foreach ($posts as $i => $post) {
            $posts[$i] = $this->postProcess($post);
        }

        return $posts;
    }

    /**
     * Blog post database retrieval post-processing - D.R.Y.
     *
     * @param array $post
     * @return array
     */
    protected function postProcess(array $post = []): array
    {
        if (empty($post)) {
            return [];
        }
        $post['tags'] = $this->getPostTags($post['postid']);
        if ($post['blogmonth'] < 10) {
            // Left-pad with a single zero
            $post['blogmonth'] = '0'.$post['blogmonth'];
        }
        return $post;
    }
}
