<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Landing;

use \Airship\Cabin\Bridge\{
    Blueprint as BP,
    Exceptions\UserFeedbackException
};
use \Airship\Cabin\Bridge\Filter\Author\{
    AuthorFilter,
    UsersFilter
};
use \Airship\Engine\Bolt\Orderable as OrderableBolt;

require_once __DIR__.'/init_gear.php';

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

    /**
     * This function is called after the dependencies have been injected by
     * AutoPilot. Think of it as a user-land constructor.
     */
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
        $post = $this->post(new AuthorFilter());
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
        $authorId = (int) $authorId;

        if (!$this->isSuperUser()) {
            $authorsForUser = $this->author->getAuthorIdsForUser(
                $this->getActiveUserId()
            );
            // Check
            if (!\in_array($authorId, $authorsForUser)) {
                \Airship\redirect($this->airship_cabin_prefix . '/author/');
            }
        }

        $post = $this->post(new AuthorFilter());
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

        // We're just grabbing counts here:
        foreach ($authors as $idx => $auth) {
            $auth['authorid'] = (int) $auth['authorid'];
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

        $this->lens(
            'author/index',
            [
                'authors' => $authors,
                'sort' => $sort,
                'dir' => $dir
            ]
        );
    }

    /**
     * Manage your author profile's photos
     *
     * @param string $authorId
     * @route author/photos/{id}
     */
    public function photos(string $authorId = '')
    {
        $authorId = (int) $authorId;
        if (!$this->isSuperUser()) {
            $authorsForUser = $this->author->getAuthorIdsForUser(
                $this->getActiveUserId()
            );
            if (!\in_array($authorId, $authorsForUser)) {
                \Airship\redirect($this->airship_cabin_prefix . '/author');
            }
        }
        $author = $this->author->getById($authorId);
        $contexts = $this->author->getPhotoContexts();
        $cabins = $this->getCabinNames();

        $this->lens(
            'author/photos',
            [
                'author' =>
                    $author,
                'contexts' =>
                    $contexts,
                'cabins' =>
                    $cabins
            ]
        );
    }

    /**
     * Manage the users that have access to this author
     *
     * @route author/users/{id}
     * @param string $authorId
     */
    public function users(string $authorId = '')
    {
        $authorId = (int) $authorId;
        if ($this->isSuperUser()) {
            $inCharge = true;
        } else {
            $authorsForUser = $this->author->getAuthorIdsForUser(
                $this->getActiveUserId()
            );
            // Check
            if (!\in_array($authorId, $authorsForUser)) {
                \Airship\redirect($this->airship_cabin_prefix . '/author');
            }
            $inCharge = $this->author->userIsOwner($authorId);
        }

        // Only someone in charge can add/remove users:
        if ($inCharge) {
            $post = $this->post(new UsersFilter());
            if ($post) {
                if ($this->manageAuthorUsers($authorId, $post)) {
                    \Airship\redirect($this->airship_cabin_prefix . '/author/users/' . $authorId);
                }
            }
        }

        $this->lens(
            'author/users',
            [
                'author' => $this->author->getById($authorId),
                'inCharge' => $inCharge,
                'users' => $this->author->getUsersForAuthor($authorId)
            ]
        );
    }

    /**
     * Add/remove users, toggle ownership status.
     *
     * @param int $authorId
     * @param array $post
     * @return bool
     */
    protected function manageAuthorUsers(int $authorId, array $post): bool
    {
        try {
            if (!empty($post['btnAddUser'])) {
                return $this->author->addUserByUniqueId(
                    $authorId,
                    $post['add_user'],
                    !empty($post['in_charge'])
                );
            } elseif (!empty($post['remove_user'])) {
                return $this->author->removeUserByUniqueId(
                    $authorId,
                    $post['remove_user']
                );
            } elseif (!empty($post['toggle_owner'])) {
                return $this->author->toggleOwnerStatus(
                    $authorId,
                    $post['toggle_owner']
                );
            }
        } catch (UserFeedbackException $ex) {
            $this->storeLensVar('form_error', (string) $ex);
        }
        return false;
    }
}
