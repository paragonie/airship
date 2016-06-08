<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Landing;

use \Airship\Cabin\Bridge\Blueprint\Author;
use \Airship\Cabin\Bridge\Landing\Proto\FileManager;

require_once __DIR__.'/init_gear.php';

/**
 * Class AuthorFiles
 * @package Airship\Cabin\Bridge\Landing
 */
class AuthorFiles extends FileManager
{
    /**
     * @var Author
     */
    protected $author;
    protected $authorId = 0;
    protected $authorSlug = '';

    public function airshipLand()
    {
        parent::airshipLand();
        $this->author = $this->blueprint('Author');
    }

    /**
     * @route author/files/{id}/{string}/delete
     * @param string $authorId
     * @param string $cabin
     */
    public function confirmDeleteFile(string $authorId, string $cabin = '')
    {
        $this->loadAuthorInfo((int) $authorId);
        $this->files->ensureDirExists($this->root_dir, $cabin);

        $dir = $this->determinePath($cabin);

        if (!\in_array($cabin, $this->getCabinNamespaces())) {
            \Airship\redirect($this->airship_cabin_prefix);
            $this->files->ensureDirExists($this->root_dir . '/photos', $cabin);
        }

        if (empty($_GET['file'])) {
            return $this->commonConfirmDeleteDir($dir, $cabin);
        }
        return $this->commonConfirmDeleteFile($_GET['file'], $dir, $cabin);
    }

    /**
     * @route author/files/{id}/{string}/info
     * @param string $authorId
     * @param string $cabin
     */
    public function getFileInfo(string $authorId, string $cabin = '')
    {
        $this->loadAuthorInfo((int) $authorId);
        $this->files->ensureDirExists($this->root_dir, $cabin);
        $this->files->ensureDirExists($this->root_dir . '/photos', $cabin);

        $dir = $this->determinePath($cabin);
        if (!\in_array($cabin, $this->getCabinNamespaces())) {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        return $this->commonGetFileInfo($_GET['file'], $dir, $cabin);
    }

    /**
     * @route author/files/{id}/{string}
     * @param string $authorId
     * @param string $cabin
     */
    public function index(string $authorId, string $cabin = '')
    {
        $this->loadAuthorInfo((int) $authorId);
        $this->files->ensureDirExists($this->root_dir, $cabin);
        $this->files->ensureDirExists($this->root_dir . '/photos', $cabin);

        $dir = $this->determinePath($cabin);
        if (!\in_array($cabin, $this->getCabinNamespaces())) {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        return $this->commonIndex($dir, $cabin);
    }

    /**
     * @route author/files/{id}/{string}/move
     * @param string $authorId
     * @param string $cabin
     */
    public function moveFile(string $authorId, string $cabin = '')
    {
        $this->loadAuthorInfo((int) $authorId);
        $this->files->ensureDirExists($this->root_dir, $cabin);
        $this->files->ensureDirExists($this->root_dir . '/photos', $cabin);

        $dir = $this->determinePath($cabin);
        if (!\in_array($cabin, $this->getCabinNamespaces())) {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        if (empty($_GET['file'])) {
            return $this->commonMoveDir($dir, $cabin);
        }
        return $this->commonMoveFile($_GET['file'], $dir, $cabin);
    }

    /**
     * @route author/files/{id}
     * @param string $authorId
     */
    public function selectCabin(string $authorId = '')
    {
        $this->loadAuthorInfo((int) $authorId);
        $this->commonSelectCabin();
    }

    /**
     * Permissions check
     *
     * @return bool
     */
    protected function permCheck(): bool
    {
        if ($this->isSuperUser()) {
            return true;
        }
        $authorsForUser = $this->author->getAuthorIdsForUser(
            $this->getActiveUserId()
        );
        // Check
        if (!\in_array($this->authorId, $authorsForUser)) {
            return false;
        }
        return true;
    }

    /**
     * Loads all the necessary information for this author
     *
     * @param int $authorId
     */
    protected function loadAuthorInfo(int $authorId)
    {
        $this->authorId = $authorId;
        $this->authorSlug = $this->author->getSlug($authorId);
        $this->storeLensVar(
            'header',
            \__(
                'Files for Author "%s"', 'default',
                $this->author->getName($authorId)
            )
        );
        $this->root_dir = 'author/' . $this->authorSlug;
        $this->path_middle = 'author/files/' . $authorId;
        $this->storeLensVar('path_middle', $this->path_middle);
        $userId = $this->getActiveUserId();
        $this->attribution = [
            'author' => $authorId,
            'uploaded_by' => $userId
        ];
    }
}
