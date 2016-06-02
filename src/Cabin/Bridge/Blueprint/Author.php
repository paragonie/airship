<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Blueprint;

use \Airship\Cabin\Bridge\Exceptions\UserFeedbackException;
use \Airship\Engine\Bolt\{
    Common,
    Orderable,
    Slug
};

require_once __DIR__.'/init_gear.php';

/**
 * Class Author
 *
 * This contains all of the methods used for managing authors.
 * It's mostly used by the Author landing, although some methods
 * are used in Airship\Cabin\Bridge\Landing\Blog as well.
 *
 * @package Airship\Cabin\Bridge\Blueprint
 */
class Author extends BlueprintGear
{
    use Common;
    use Orderable;
    use Slug;

    protected $photosDir = 'photos';

    /**
     * Add a user, given its unique id.
     *
     * @param int $authorId
     * @param string $uniqueId
     * @param bool $inCharge
     * @return bool
     * @throws UserFeedbackException
     */
    public function addUserByUniqueId(
        int $authorId,
        string $uniqueId,
        bool $inCharge = false
    ): bool
    {
        $this->db->beginTransaction();
        $userID = (int)$this->db->cell(
            'SELECT userid FROM airship_users WHERE uniqueid = ?',
            $uniqueId
        );

        if (empty($userID)) {
            throw new UserFeedbackException(
                'There is no user with this Public ID.'
            );
        }
        if ($this->userHasAccess($authorId, $userID)) {
            throw new UserFeedbackException(
                'This User already has access to this Author.'
            );
        }
        $this->db->insert(
            'hull_blog_author_owners',
            [
                'authorid' => $authorId,
                'userid' => $userID,
                'in_charge' => $inCharge
            ]
        );
        return $this->db->commit();
    }

    /**
     * Create a new Author profile
     *
     * @param array $post
     * @return bool
     */
    public function createAuthor(array $post): bool
    {
        $this->db->beginTransaction();
        $slug = $this->makeGenericSlug(
            $post['name'],
            'hull_blog_authors',
            'slug'
        );
        $authorId = $this->db->insertGet(
            'hull_blog_authors', [
            'name' =>
                $post['name'],
            'byline' =>
                $post['byline'] ?? '',
            'bio_format' =>
                $post['format'] ?? 'Markdown',
            'biography' =>
                $post['biography'] ?? '',
            'slug' =>
                $slug
        ],
            'authorid'
        );
        $this->db->insert(
            'hull_blog_author_owners', [
                'authorid' =>
                    $authorId,
                'userid' =>
                    $this->getActiveUserId(),
                'in_charge' =>
                    true
            ]
        );

        return $this->db->commit();
    }

    /**
     * Get all authors, sorting by a particular field
     *
     * @param string $sortby
     * @param string $dir
     * @return array
     */
    public function getAll(string $sortby = 'name', string $dir = 'ASC'): array
    {
        return $this->db->run('SELECT * FROM view_hull_users_authors ' . $this->orderBy($sortby, $dir, ['name', 'created']));
    }

    /**
     * Get all of the authors available for this user to post under
     *
     * @param int $userid
     * @return array
     */
    public function getAuthorIdsForUser(int $userid): array
    {
        $authors = $this->db->col('SELECT authorid FROM view_hull_users_authors WHERE userid = ?', 0, $userid);
        if (empty($authors)) {
            return [];
        }
        return $authors;
    }

    /**
     * Get all of the photos in the various
     *
     * 1. Get the directories for each cabin.
     * 2. Get all of the file IDs for these directories.
     *    Our SQL query should automatically draw in the "context" parameter.
     *
     * @param int $authorID
     * @return array
     */
    public function getAvailablePhotos(int $authorID): array
    {
        $slug = $this->db->cell('SELECT slug FROM hull_blog_authors WHERE authorid = ?', $authorID);
        $directoryIDs = [];
        foreach ($this->getCabinNames() as $cabin) {
            $cabinDir = $this->getPhotoDirectory($slug, $cabin);
            if (!empty($cabinDir)) {
                $directoryIDs []= $cabinDir;
            }
        }
        if (empty($directoryIDs)) {
            return [];
        }
        $files = $this->db->run(
            "SELECT
                 f.*,
                 p.context
             FROM 
                 airship_files f
             LEFT JOIN
                 hull_blog_author_photos p
                 ON p.file = f.fileid
             WHERE
                 (f.type LIKE 'image/%' OR f.type LIKE 'x-image/%') 
                     AND
                 f.directory IN " . $this->db->escapeValueSet($directoryIDs, 'int')
        );
        if (empty($files)) {
            return [];
        }
        return $files;
    }

    /**
     * Get an author by its ID
     *
     * @param int $authorId
     * @return array
     */
    public function getById(int $authorId): array
    {
        $author = $this->db->row(
            'SELECT * FROM hull_blog_authors WHERE authorid = ?',
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
     * @param string $sortby
     * @param string $dir
     * @return array
     */
    public function getForUser(int $userId, string $sortby = 'name', string $dir = 'ASC'): array
    {
        $authors = $this->db->run(
            'SELECT * FROM view_hull_users_authors WHERE userid = ?' . $this->orderBy($sortby, $dir, ['name', 'created']),
            $userId
        );
        if (empty($authors)) {
            return [];
        }
        return $authors;
    }

    /**
     * Get the number of photos uploaded for this author
     *
     * @param int $authorId
     * @return int
     */
    public function getNumBlogPostsForAuthor(int $authorId): int
    {
        $num = $this->db->cell(
            'SELECT count(postid) FROM hull_blog_posts WHERE author = ?',
            $authorId
        );
        if ($num > 0) {
            return $num;
        }
        return 0;
    }

    /**
     * Get the number of photos uploaded for this author
     *
     * @param int $authorId
     * @return int
     */
    public function getNumCommentsForAuthor(int $authorId): int
    {
        $num = $this->db->cell(
            'SELECT count(commentid) FROM hull_blog_comments WHERE author = ?',
            $authorId
        );
        if ($num > 0) {
            return $num;
        }
        return 0;
    }

    /**
     * Get the number of photos uploaded for this author
     *
     * @param int $authorId
     * @return int
     */
    public function getNumFilesForAuthor(int $authorId): int
    {
        $num = $this->db->cell(
            'SELECT count(fileid) FROM airship_files WHERE author = ?',
            $authorId
        );
        if ($num > 0) {
            return $num;
        }
        return 0;
    }

    /**
     * Get the number of users that have access to this author
     *
     * @param int $authorId
     * @return int
     */
    public function getNumUsersForAuthor(int $authorId): int
    {
        $num = $this->db->cell(
            'SELECT count(userid) FROM hull_blog_author_owners WHERE authorid = ?',
            $authorId
        );
        if ($num > 0) {
            return $num;
        }
        return 0;
    }

    /**
     * Get the available photo contexts
     *
     * @return array
     */
    public function getPhotoContexts(): array
    {
        $result = $this->db->run('SELECT * FROM hull_blog_photo_contexts ORDER BY display_name ASC');
        if (empty($result)) {
            return [];
        }
        return $result;
    }

    /**
     * Get photos directory ID
     *
     * @param $slug
     * @param $cabin
     * @return int
     */
    protected function getPhotoDirectory($slug, $cabin): int
    {
        // Start with the cabin
        $root = $this->db->cell(
            'SELECT directoryid FROM airship_dirs WHERE cabin = ? AND name = ? AND parent IS NULL',
            $cabin,
            'author'
        );
        if (empty($root)) {
            return 0;
        }

        $myBaseDir = $this->db->cell(
            'SELECT directoryid FROM airship_dirs WHERE name = ? AND parent = ?',
            $slug,
            $root
        );
        if (empty($myBaseDir)) {
            return 0;
        }


        $photoDir = $this->db->cell(
            'SELECT directoryid FROM airship_dirs WHERE name = ? AND parent = ?',
            $this->photosDir,
            $myBaseDir
        );
        if (empty($photoDir)) {
            return 0;
        }
        return $photoDir;
    }

    /**
     * Get the slug
     *
     * @param int $authorId
     * @return string
     */
    public function getSlug(int $authorId): string
    {
        $slug = $this->db->cell('SELECT slug FROM hull_blog_authors WHERE authorid = ?', $authorId);
        if (!empty($slug)) {
            return $slug;
        }
        return '';
    }

    /**
     * Get the users
     *
     * @param int $authorId
     * @return array
     */
    public function getUsersForAuthor(int $authorId): array
    {
        $queryString = 'SELECT
                *
            FROM
                view_hull_users_authors  
            WHERE
                authorid = ? 
            ORDER BY
                in_charge ASC, uniqueid ASC';

        $authors = $this->db->run(
            $queryString,
            $authorId
        );
        if (empty($authors)) {
            return [];
        }
        return $authors;
    }

    /**
     * 
     * @return int
     */
    public function numAuthors(): int
    {
        return (int) $this->db->cell('SELECT count(authorid) FROM hull_blog_authors');
    }

    /**
     * Remove a user from this author
     *
     * @param int $authorId
     * @param string $uniqueId
     * @return bool
     * @throws UserFeedbackException
     */
    public function removeUserByUniqueId(
        int $authorId,
        string $uniqueId
    ): bool {
        $this->db->beginTransaction();
        $userID = (int) $this->db->cell(
            'SELECT userid FROM airship_users WHERE uniqueid = ?',
            $uniqueId
        );

        if (empty($userID)) {
            throw new UserFeedbackException(
                'There is no user with this Public ID.'
            );
        }
        if (!$this->userHasAccess($authorId, $userID)) {
            throw new UserFeedbackException(
                "This User doesn't have access to this Author."
            );
        }
        $this->db->delete(
            'hull_blog_author_owners',
            [
                'authorid' => $authorId,
                'userid' => $userID
            ]
        );
        return $this->db->commit();
    }

    /**
     * Toggle the 'owner' flag.
     *
     * @param int $authorId
     * @param string $uniqueId
     * @return bool
     * @throws UserFeedbackException
     */
    public function toggleOwnerStatus(
        int $authorId,
        string $uniqueId
    ): bool {
        $this->db->beginTransaction();
        $userID = (int) $this->db->cell(
            'SELECT userid FROM airship_users WHERE uniqueid = ?',
            $uniqueId
        );

        if (empty($userID)) {
            throw new UserFeedbackException(
                'There is no user with this Public ID.'
            );
        }
        if (!$this->userHasAccess($authorId, $userID)) {
            throw new UserFeedbackException(
                "This User doesn't have access to this Author."
            );
        }
        $this->db->update(
            'hull_blog_author_owners',
            [
                'in_charge' => !$this->userIsOwner($authorId, $userID)
            ],
            [
                'authorid' => $authorId,
                'userid' => $userID
            ]
        );
        return $this->db->commit();
    }

    /**
     * Update an author profile
     *
     * @param int $authorId
     * @param array $post
     * @return bool
     */
    public function updateAuthor(int $authorId, array $post): bool
    {
        $this->db->beginTransaction();

        $this->db->update(
            'hull_blog_authors',
            [
                'name' =>
                    $post['name'] ?? '',
                'byline' =>
                    $post['byline'] ?? '',
                'bio_format' =>
                    $post['format'] ?? 'Markdown',
                'biography' =>
                    $post['biography'] ?? ''
            ],
            [
                'authorid' => $authorId
            ]
        );

        return $this->db->commit();
    }

    /**
     * Is this user an owner of the given
     *
     * @param int $authorId
     * @param int $userId
     * @return bool
     */
    public function userHasAccess(int $authorId, int $userId = 0): bool
    {
        if ($userId < 1) {
            $userId = $this->getActiveUserId();
        }
        return $this->db->cell(
            'SELECT count(*) FROM view_hull_users_authors WHERE authorid = ? AND userid = ?',
            $authorId,
            $userId
        ) > 0;
    }

    /**
     * Is this user an owner of the given
     *
     * @param int $authorId
     * @param int $userId
     * @return bool
     */
    public function userIsOwner(int $authorId, int $userId = 0): bool
    {
        if ($userId < 1) {
            $userId = $this->getActiveUserId();
        }
        return $this->db->cell(
            'SELECT in_charge FROM view_hull_users_authors WHERE authorid = ? AND userid = ?',
            $authorId,
            $userId
        );
    }
}
