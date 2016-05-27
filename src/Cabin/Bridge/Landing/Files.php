<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Landing;

use \Airship\Cabin\Bridge\Landing\Proto\FileManager;

require_once __DIR__.'/init_gear.php';

/**
 * Class Files
 * @package Airship\Cabin\Bridge\Landing
 */
class Files extends FileManager
{
    /**
     * Administrators only!
     */
    public function airshipLand()
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
        $this->storeLensVar('path_middle', $this->path_middle);
    }

    /**
     * @route file_manager/{string}/delete
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
     * @route file_manager/{string}/info
     * @param string $cabin
     */
    public function getFileInfo(string $cabin = '')
    {
        $dir = $this->determinePath($cabin);
        if (!\in_array($cabin, $this->getCabinNamespaces())) {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        if (empty($_GET['file'])) {
            \Airship\redirect(
                $this->airship_cabin_prefix . '/'.$this->path_middle.'/' . \urlencode($cabin),
                [
                    'dir' => $dir
                ]
            );
        }
        $this->commonGetFileInfo($_GET['file'], $dir, $cabin);
    }

    /**
     * @route file_manager/{string}
     * @param string $cabin
     */
    public function index(string $cabin = '')
    {
        $dir = $this->determinePath($cabin);
        if (!\in_array($cabin, $this->getCabinNamespaces())) {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        $this->commonIndex($dir, $cabin);
    }

    /**
     * @route file_manager/{string}/move
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
     * Cabin selection interface
     * @route file_manager
     */
    public function selectCabin()
    {
        return $this->commonSelectCabin();
    }

    /**
     * Permissions check
     *
     * @return bool
     */
    protected function permCheck(): bool
    {
        return $this->isSuperUser(); // You're an admin!
    }
}
