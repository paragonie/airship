<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Controller;

use Airship\Cabin\Bridge\{
    Model\UserAccounts,
    Controller\Proto\FileManager
};
use Airship\Engine\Security\Util;

require_once __DIR__.'/init_gear.php';

/**
 * Class MyFiles
 * @package Airship\Cabin\Bridge\Controller
 */
class MyFiles extends FileManager
{
    /**
     * @var UserAccounts
     */
    protected $users;

    /**
     * @var string
     */
    protected $userUniqueId;

    /**
     * This function is called after the dependencies have been injected by
     * AutoPilot. Think of it as a user-land constructor.
     */
    public function airshipLand()
    {
        parent::airshipLand();

        $this->users = $this->model('UserAccounts');
        $userId = $this->getActiveUserId();
        $this->userUniqueId = $this->users->getUniqueId($userId);
        $this->root_dir = 'user/' . $this->userUniqueId;
        $this->files->ensureDirExists($this->root_dir);
        $this->path_middle = 'my/files';
        $this->attribution = [
            'author' => null,
            'uploaded_by' => $userId
        ];
        $this->storeViewVar('path_middle', $this->path_middle);
        $this->storeViewVar('active_link', 'bridge-link-my-files');
        $this->storeViewVar('header', 'My Files');
        $this->storeViewVar('title', 'My Files');
    }

    /**
     * @route my/files/{string}/delete
     * @param string $cabin
     */
    public function confirmDeleteFile(string $cabin = '')
    {
        $dir = $this->determinePath($cabin);
        if (!\in_array($cabin, $this->getCabinNamespaces())) {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        if (empty($_GET['file'])) {
            $this->commonConfirmDeleteDir($dir, $cabin);
            return;
        }
        $this->commonConfirmDeleteFile($_GET['file'], $dir, $cabin);
    }

    /**
     * @route my/files/{string}/info
     * @param string $cabin
     * @throws \TypeError
     */
    public function getFileInfo(string $cabin = '')
    {
        $this->files->ensureDirExists($this->root_dir, $cabin);
        $dir = $this->determinePath($cabin);
        if (empty($_GET['file'])) {
            \Airship\redirect(
                $this->airship_cabin_prefix . '/my_files/' . \urlencode($cabin),
                [
                    'dir' => $dir
                ]
            );
        }
        if (!\is_string($_GET['file'])) {
            throw new \TypeError('String expected');
        }
        $fileName = $_GET['file'];
        if (!\in_array($cabin, $this->getCabinNamespaces())) {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        $this->storeViewVar(
            'title',
            \__(
                '%s', 'default',
                Util::noHTML(!empty($dir)
                    ? $dir . '/' . $fileName
                    : $fileName
                )
            )
        );
        $this->commonGetFileInfo($fileName, $dir, $cabin);
    }

    /**
     * @route my/files/{string}
     * @param string $cabin
     */
    public function index(string $cabin = '')
    {
        $this->files->ensureDirExists($this->root_dir, $cabin);
        $dir = $this->determinePath($cabin);
        if (!\in_array($cabin, $this->getCabinNamespaces())) {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        $this->commonIndex($dir, $cabin);
    }

    /**
     * @route my/files/{string}/move
     * @param string $cabin
     */
    public function moveFile(string $cabin = '')
    {
        $dir = $this->determinePath($cabin);
        if (!\in_array($cabin, $this->getCabinNamespaces())) {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        if (empty($_GET['file'])) {
            $this->commonMoveDir($dir, $cabin);
            return;
        }
        $this->commonMoveFile($_GET['file'], $dir, $cabin);
    }

    /**
     * @route my/files
     */
    public function selectCabin()
    {
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
        return true; // You're logged in, so this is a no-brainer.
    }
}
