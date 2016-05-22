<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Landing;

use \Airship\Cabin\Bridge\Blueprint as BP;
use \Airship\Engine\Bolt\Orderable as OrderableBolt;

require_once __DIR__.'/gear.php';

/**
 * Class Author
 *
 * Manager personas.
 *
 * @package Airship\Cabin\Bridge\Landing
 */
class Author extends LoggedInUsersOnly
{
    use OrderableBolt;

    /**
     * @var BP\Author
     */
    private $author;

    public function airshipLand()
    {
        parent::airshipLand();
        $this->author = $this->blueprint('Author');
    }

    /**
     * Create a new author profile
     *
     * @route author/new
     */
    public function create()
    {
        $post = $this->post();
        if (!empty($post['name'])) {
            if ($this->author->createAuthor($post)) {
                \Airship\redirect($this->airship_cabin_prefix . '/author/');
            }
        }

        $this->lens('author/new');
    }

    /**
     * Create a new author profile
     *
     * @route author/edit/{id}
     * @param string $authorId
     */
    public function edit(string $authorId = '')
    {
        $authorId += 0; // Coerce to int

        if (!$this->isSuperUser()) {
            $authorsForUser = $this->author->getAuthorIdsForUser(
                $this->getActiveUserId()
            );
            // Check
            if (!\in_array($authorId, $authorsForUser)) {
                \Airship\redirect($this->airship_cabin_prefix . '/author/');
            }
        }

        $post = $this->post();
        if (!empty($post['name'])) {
            if ($this->author->updateAuthor($authorId, $post)) {
                \Airship\redirect($this->airship_cabin_prefix . '/author/');
            }
        }

        $this->lens('author/edit', [
            'author' => $this->author->getById($authorId),
        ]);
    }

    /**
     * Index page for blog author profiles
     *
     * @route author{_page}
     */
    public function index()
    {
        $sort = $_GET['sort'] ?? 'name';
        $dir = $_GET['dir'] ?? 'ASC';
        $dir = \strtoupper($dir);
        if ($dir !== 'ASC' && $dir !== 'DESC') {
            $dir = 'ASC';
        }

        if ($this->isSuperUser()) {
            $authors = $this->author->getAll($sort, $dir);
        } else {
            $authors = $this->author->getForUser(
                $this->getActiveUserId(),
                $sort,
                $dir
            );
        }
        foreach ($authors as $idx => $auth) {
            $authors[$idx]['num_users'] = $this->author->getNumUsersForAuthor(
                $auth['authorid']
            );
            $authors[$idx]['num_comments'] = $this->author->getNumCommentsForAuthor(
                $auth['authorid']
            );
            $authors[$idx]['num_files'] = $this->author->getNumFilesForAuthor(
                $auth['authorid']
            );
            $authors[$idx]['num_blog_posts'] = $this->author->getNumBlogPostsForAuthor(
                $auth['authorid']
            );
        }

        switch ($sort) {
            case 'blog_posts':
                $this->sortArrayByIndex($authors, 'num_blog_posts', $dir === 'DESC');
                break;
            case 'comments':
                $this->sortArrayByIndex($authors, 'num_comments', $dir === 'DESC');
                break;
            case 'files':
                $this->sortArrayByIndex($authors, 'num_files', $dir === 'DESC');
                break;
            case 'users':
                $this->sortArrayByIndex($authors, 'num_users', $dir === 'DESC');
                break;
        }

        $this->lens('author/index', [
            'authors' => $authors,
            'sort' => $sort,
            'dir' => $dir
        ]);
    }

    /**
     * Manage the users that have access to this author
     *
     * @route author/users/{id}
     * @param string $authorId
     */
    public function users(string $authorId = '')
    {
        $authorId += 0; // Coerce to int

        if (!$this->isSuperUser()) {
            $authorsForUser = $this->author->getAuthorIdsForUser(
                $this->getActiveUserId()
            );
            // Check
            if (!\in_array($authorId, $authorsForUser)) {
                \Airship\redirect($this->airship_cabin_prefix . '/author/');
            }
        }
    }
}
