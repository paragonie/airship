<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Controller;

use Airship\Cabin\Bridge\Model\Author;
use Airship\Cabin\Bridge\Controller\Proto\FileManager;
use Airship\Engine\Security\Util;

require_once __DIR__.'/init_gear.php';

/**
 * Class AuthorFiles
 * @package Airship\Cabin\Bridge\Controller
 */
class AuthorFiles extends FileManager
{
    /**
     * @var Author
     */
    protected $author;

    /**
     * @var int
     */
    protected $authorId = 0;

    /**
     * @var string
     */
    protected $authorName = '';

    /**
     * @var string
     */
    protected $authorSlug = '';

    /**
     * This function is called after the dependencies have been injected by
     * AutoPilot. Think of it as a user-land constructor.
     */
    public function airshipLand()
    {
        parent::airshipLand();
        $this->author = $this->model('Author');
        $this->storeViewVar('active_link', 'bridge-link-authors');
        $this->storeViewVar('title', \__('Author\'s Files'));
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
        $this->storeViewVar('title', \__('Confirm file delete'));
        return $this->commonConfirmDeleteFile($_GET['file'], $dir, $cabin);
    }

    /**
     * @route author/files/{id}/{string}/info
     * @param string $authorId
     * @param string $cabin
     * @throws \TypeError
     */
    public function getFileInfo(string $authorId, string $cabin = '')
    {
        $this->loadAuthorInfo((int) $authorId);
        $this->files->ensureDirExists($this->root_dir, $cabin);
        $this->files->ensureDirExists($this->root_dir . '/photos', $cabin);
        if (empty($_GET['file'])) {
            \Airship\redirect(
                $this->airship_cabin_prefix . '/author/files/' . $this->authorId . '/' . \urlencode($cabin)
            );
        }
        if (!\is_string($_GET['file'])) {
            throw new \TypeError('String expected');
        }
        $fileName = $_GET['file'];

        $dir = $this->determinePath($cabin);
        if (!\in_array($cabin, $this->getCabinNamespaces())) {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        $this->storeViewVar(
            'title',
            \__(
                '%s (Author: %s)', 'default',
                Util::noHTML(!empty($dir)
                    ? $dir . '/' . $fileName
                    : $fileName
                ),
                $this->authorName
            )
        );
        return $this->commonGetFileInfo($fileName, $dir, $cabin);
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

    /** @noinspection PhpMissingParentCallCommonInspection */
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
        $this->authorName = $this->author->getName($authorId);
        $this->authorSlug = $this->author->getSlug($authorId);
        $this->storeViewVar(
            'header',
            \__(
                'Files for Author "%s"', 'default',
                Util::noHTML($this->authorName)
            )
        );
        $this->storeViewVar(
            'title',
            \__(
                'Files for Author "%s"', 'default',
                Util::noHTML($this->authorName)
            )
        );
        $this->root_dir = 'author/' . $this->authorSlug;
        $this->path_middle = 'author/files/' . $authorId;
        $this->storeViewVar('path_middle', $this->path_middle);
        $userId = $this->getActiveUserId();
        $this->attribution = [
            'author' => $authorId,
            'uploaded_by' => $userId
        ];
    }
}
