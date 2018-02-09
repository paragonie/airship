<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Controller;

use Airship\Cabin\Bridge\Controller\Proto\FileManager;

require_once __DIR__.'/init_gear.php';

/**
 * Class Files
 * @package Airship\Cabin\Bridge\Controller
 */
class Files extends FileManager
{
    /**
     * This function is called after the dependencies have been injected by
     * AutoPilot. Think of it as a user-land constructor.
     */
    public function airshipLand(): void
    {
        parent::airshipLand();

        if (!$this->isSuperUser()) {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        $this->path_middle = 'file_manager';
        $userId = $this->getActiveUserId();
        $this->attribution = [
            'author' => null,
            'uploaded_by' => $userId
        ];
        $this->storeViewVar('path_middle', $this->path_middle);
        $this->storeViewVar('header', 'Files');
    }

    /**
     * @route file_manager/{string}/delete
     * @param string $cabin
     */
    public function confirmDeleteFile(string $cabin = ''): void
    {
        $dir = $this->determinePath($cabin);
        if (!\in_array($cabin, $this->getCabinNamespaces(), true)) {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        $this->setTemplateExtraData($cabin);
        if (empty($_GET['file'])) {
            $this->commonConfirmDeleteDir($dir, $cabin);
            return;
        }
        $this->commonConfirmDeleteFile($_GET['file'], $dir, $cabin);
    }

    /**
     * @route file_manager/{string}/info
     * @param string $cabin
     */
    public function getFileInfo(string $cabin = ''): void
    {
        $dir = $this->determinePath($cabin);
        if (!\in_array($cabin, $this->getCabinNamespaces(), true)) {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        $this->setTemplateExtraData($cabin);
        if (empty($_GET['file'])) {
            \Airship\redirect(
                $this->airship_cabin_prefix . '/' . $this->path_middle . '/' . \urlencode($cabin),
                [
                    'dir' => $dir
                ]
            );
        }
        $this->storeViewVar('active_submenu', ['Cabins', 'Cabin__' . $cabin]);
        $this->commonGetFileInfo($_GET['file'], $dir, $cabin);
    }

    /**
     * @route file_manager/{string}
     * @param string $cabin
     */
    public function index(string $cabin = ''): void
    {
        $dir = $this->determinePath($cabin);
        if (!\in_array($cabin, $this->getCabinNamespaces(), true)) {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        $this->setTemplateExtraData($cabin);
        $this->commonIndex($dir, $cabin);
    }

    /**
     * @route file_manager/{string}/move
     * @param string $cabin
     */
    public function moveFile(string $cabin = ''): void
    {
        $dir = $this->determinePath($cabin);
        if (!\in_array($cabin, $this->getCabinNamespaces(), true)) {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        $this->setTemplateExtraData($cabin);
        if (empty($_GET['file'])) {
            $this->commonMoveDir($dir, $cabin);
            return;
        }
        $this->commonMoveFile($_GET['file'], $dir, $cabin);
    }

    /**
     * Cabin selection interface
     * @route file_manager
     */
    public function selectCabin(): void
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
        return $this->isSuperUser(); // You're an admin!
    }

    /**
     * Set the cabin links
     *
     * @param string $cabin
     */
    protected function setTemplateExtraData(string $cabin): void
    {
        $this->storeViewVar(
            'active_submenu',
            [
                'Cabins',
                'Cabin__' . $cabin
            ]
        );
        $this->storeViewVar(
            'active_link',
            'bridge-link-cabin-' . $cabin . '-files'
        );
    }
}
