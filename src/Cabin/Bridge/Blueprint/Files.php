<?php
namespace Airship\Cabin\Bridge\Blueprint;

use \Airship\Alerts\FileSystem\{
    FileNotFound,
    UploadError
};
use \Airship\Engine\{
    Database,
    State
};
use \ParagonIE\Halite\File as HaliteFile;

require_once __DIR__.'/init_gear.php';

/**
 * Class Files
 *
 * Manage user-provided files.
 *
 * @package Airship\Cabin\Bridge\Blueprint
 */
class Files extends BlueprintGear
{
    /**
     * We rename these to .txt internally just to avoid stupid errors down the road.
     * This affects the real file name, not the virtual one the user sees.
     *
     * @var array
     */
    protected $badFileExtensions = [
        'php',
        'php3',
        'php4',
        'php5',
        'php7', // Stranger things have happened!
        'phtml',
        'html',
        'js',
        'pl',
        'py',
        'sh'
    ];
    protected $finfo;

    /**
     * Files constructor.
     * @param Database $db
     */
    public function __construct(Database $db)
    {
        parent::__construct($db);
        $this->finfo = new \finfo(FILEINFO_MIME);
    }

    /**
     * Create a new directory
     *
     * @param int|null $parent
     * @param string $cabin
     * @param string $dirName
     * @return bool
     * @throws \TypeError
     */
    public function createDirectory(
        $parent = null,
        string $cabin = '',
        string $dirName = ''
    ): bool {
        $this->db->beginTransaction();
        $this->db->insert(
            'airship_dirs',
            [
                'name' => $dirName,
                'parent' => $parent > 0
                    ? $parent
                    : null,
                'cabin' => $cabin
            ]
        );
        return $this->db->commit();
    }

    /**
     * Delete a directory
     *
     * @param string $cabin
     * @param string $root
     * @param string $subdirectory
     * @return bool
     */
    public function deleteDir(string $cabin, string $root, string $subdirectory): bool
    {
        $dir = empty($root)
            ? $subdirectory
            : $root . '/' . $subdirectory;
        $pieces = \Airship\chunk($dir);
        $dirId = $this->getDirectoryId($pieces, $cabin);
        return $this->recursiveDelete($dirId, $cabin);
    }

    /**
     * Delete a file
     *
     * @param array $fileInfo
     * @return bool
     */
    public function deleteFile(array $fileInfo): bool
    {
        $this->db->beginTransaction();
        if (\file_exists(\AIRSHIP_UPLOADS . $fileInfo['realname'])) {
            \unlink(\AIRSHIP_UPLOADS . $fileInfo['realname']);
        }
        // Remove associations with this file
        $this->db->update(
            'hull_blog_author_photos',
            [
                'file' => null
            ],
            [
                'file' => $fileInfo['fileid']
            ]
        );
        $this->db->delete(
            'airship_files',
            [
                'fileid' => $fileInfo['fileid']
            ]
        );
        return $this->db->commit();
    }

    /**
     * Does the directory already exist?
     *
     * @param null $parent
     * @param string $cabin
     * @param string $dirName
     * @return bool
     */
    public function dirExists(
        $parent = null,
        string $cabin = '',
        string $dirName = ''
    ): bool {
        if (empty($parent)) {
            return 0 < $this->db->cell(
                'SELECT count(*) FROM airship_dirs WHERE parent IS NULL AND name = ? AND cabin = ?',
                $dirName,
                $cabin
            );
        }
        return 0 < $this->db->cell(
            'SELECT count(*) FROM airship_dirs WHERE parent = ? AND name = ? AND cabin = ?',
            $parent,
            $dirName,
            $cabin
        );
    }

    /**
     * Programmatically ensure that the directory exists.
     *
     * @param string $absolutePath
     * @param string $cabin
     * @return bool
     */
    public function ensureDirExists(
        string $absolutePath,
        string $cabin = 'Hull'
    ): bool {
        $this->db->beginTransaction();
        $parent = null;
        $pieces = \Airship\chunk($absolutePath, '/');
        while (!empty($pieces)) {
            $piece = \array_shift($pieces);
            if (empty($piece)) {
                continue;
            }
            if (empty($parent)) {
                $dir = $this->db->row(
                    'SELECT * FROM airship_dirs WHERE parent IS NULL AND name = ? AND cabin = ?',
                    $piece,
                    $cabin
                );
            } else {
                $dir = $this->db->row(
                    'SELECT * FROM airship_dirs WHERE parent = ? AND name = ? AND cabin = ?',
                    $parent,
                    $piece,
                    $cabin
                );
            }
            // Did we get something?
            if (empty($dir)) {
                $parent = $this->db->insertGet(
                    'airship_dirs', [
                        'cabin' => $cabin,
                        'parent' => $parent,
                        'name' => $piece
                    ],
                    'directoryid'
                );
            } else {
                $parent = (int) $dir['directoryid'];
            }
        }
        return $this->db->commit();
    }

    /**
     * Get all of the directories beneath the current one
     *
     * @param mixed $directoryId (usually an integer, sometimes null)
     * @param string $cabin
     * @return array
     */
    public function getChildrenOf($directoryId = null, string $cabin = ''): array
    {
        if (empty($directoryId)) {
            $children = $this->db->run(
                'SELECT * FROM airship_dirs WHERE parent IS NULL AND cabin = ? ORDER BY name ASC',
                $cabin
            );
        } else {
            $children = $this->db->run(
                'SELECT * FROM airship_dirs WHERE parent = ? AND cabin = ? ORDER BY name ASC',
                $directoryId,
                $cabin
            );
        }
        if (empty($children)) {
            return [];
        }
        return $children;
    }

    /**
     * @param string $cabin
     * @param string $base
     * @param string $thisDir
     * @return array
     */
    public function getContentsTree(string $cabin, string $base, string $thisDir): array
    {
        $pieces = \Airship\chunk($base);
        foreach (\Airship\chunk($thisDir) as $p) {
            \array_push($pieces, $p);
        }
        try {
            $dirId = $this->getDirectoryId($pieces, $cabin);
        } catch (FileNotFound $ex) {
            return [];
        }
        return $this->getContentsIterative($dirId, $thisDir, $cabin);
    }

    /**
     * Get a list of all the files in a directory.
     *
     * @param int $parent
     * @param string $path
     * @param string $cabin
     * @return array
     */
    protected function getContentsIterative(int $parent, string $path, string $cabin): array
    {
        $ret = [];
        foreach ($this->getChildrenOf($parent, $cabin) as $dir) {
            $list = $this->getContentsIterative(
                (int) $dir['directoryid'],
                $path . '/' . $dir['name'],
                $cabin
            );
            foreach ($list as $l) {
                $ret []= $l;
            }
        }
        foreach ($this->getFilesInDirectory($parent) as $f) {
            $ret []= $path . '/' . $f['filename'];
        }
        return $ret;
    }

    /**
     * Given a directory ID, get its cabin.
     *
     * @param int $directoryId
     * @return string
     */
    public function getDirectoryCabin(int $directoryId): string
    {
        do {
            $parent = $this->db->row(
                "SELECT parent, cabin FROM airship_dirs WHERE directoryid = ?",
                $directoryId
            );
            $directoryId = $parent['parent'];
        } while ($directoryId !== null);
        return $parent['cabin'];
    }

    /**
     * Get a directory ID, should it exist.
     *
     * @param array $parts
     * @param string $cabin
     * @return int
     * @throws FileNotFound
     */
    public function getDirectoryId(array $parts, string $cabin): int
    {
        $part = \array_shift($parts);
        $parent = $this->db->cell(
            'SELECT directoryid FROM airship_dirs WHERE parent IS NULL AND name = ? AND cabin = ?',
            $part,
            $cabin
        );
        if (empty($parent)) {
            throw new FileNotFound();
        }

        foreach ($parts as $part) {
            $parent = $this->db->cell(
                'SELECT * FROM airship_dirs WHERE name = ? AND parent = ? AND cabin = ?',
                $part,
                $parent,
                $cabin
            );
            if (empty($parent)) {
                throw new FileNotFound();
            }
        }
        return $parent;
    }

    /**
     * @param string $cabin
     * @param string $rootDir
     * @param string $ignore
     * @param string $pieces
     * @return array
     */
    public function getDirectoryTree(
        string $cabin = '',
        string $rootDir = '',
        string $ignore = '',
        string $pieces = ''
    ): array {
        if (empty($rootDir)) {
            $children = $this->db->run(
                'SELECT * FROM airship_dirs WHERE parent IS NULL AND cabin = ? ORDER BY name ASC',
                $cabin
            );
        } else {
            $children = $this->db->run(
                'SELECT * FROM airship_dirs WHERE parent = ? AND cabin = ? ORDER BY name ASC',
                $this->getDirectoryId(\Airship\chunk($rootDir), $cabin),
                $cabin
            );
            $rootDir .= '/';
        }
        if (!empty($pieces)) {
            $pieces .= '/';
        }
        $dirs = [];
        foreach ($children as $child) {
            if (!empty($ignore)) {
                if (\strpos($pieces . $child['name'] . '/', $ignore) !== false) {
                    // We're ignoring this
                    continue;
                }
            }
            $dirs []= $pieces . $child['name'];
            $subDirs = $this->getDirectoryTree(
                $cabin,
                $rootDir . $child['name'],
                $ignore,
                $pieces . $child['name']
            );
            foreach ($subDirs as $sub) {
                $dirs [] = $sub;
            }
        }
        return $dirs;
    }

    /**
     * Get all of the directories beneath the current one
     *
     * @param null $directoryId
     * @param string $cabin
     * @return array
     */
    public function getFilesInDirectory($directoryId = null, string $cabin = ''): array
    {
        if (empty($directoryId)) {
            $children = $this->db->run(
                'SELECT * FROM airship_files WHERE directory IS NULL AND cabin = ? ORDER BY filename ASC',
                $cabin
            );
        } else {
            $children = $this->db->run(
                'SELECT * FROM airship_files WHERE directory = ? AND cabin IS NULL ORDER BY filename ASC',
                $directoryId
            );
        }
        if (empty($children)) {
            return [];
        }
        foreach ($children as $i => $child) {
            if (\file_exists(AIRSHIP_UPLOADS . $child['realname'])) {
                $children[$i]['size'] = \filesize(AIRSHIP_UPLOADS . $child['realname']);
            } else {
                $children[$i]['size'] = 0;
            }
        }
        return $children;
    }

    /**
     * Given a file ID, get its Cabin
     *
     * @param int $fileId
     * @return string
     * @throws \Airship\Alerts\Database\QueryError
     */
    public function getFilesCabin(int $fileId): string
    {
        $fileInfo = $this->db->row(
            'SELECT directory, cabin FROM airship_files WHERE fileid = ?',
            $fileId
        );
        if (empty($fileInfo['directory'])) {
            return $fileInfo['cabin'];
        }
        return $this->getDirectoryCabin(
            (int) $fileInfo['directory']
        );
    }

    /**
     * @param string $cabin
     * @param array $path
     * @param string $filename
     * @return array
     * @throws FileNotFound
     */
    public function getFileInfo(string $cabin = '', $path = null, string $filename = ''): array
    {
        if (empty($path)) {
            $fileInfo = $this->db->row(
                'SELECT * FROM airship_files WHERE directory IS NULL AND cabin = ? AND filename = ?',
                $cabin,
                $filename
            );
        } else {
            $fileInfo = $this->db->row(
                'SELECT * FROM airship_files WHERE directory = ? AND filename = ?',
                $path,
                $filename
            );
        }
        if (\file_exists(AIRSHIP_UPLOADS . $fileInfo['realname'])) {
            $fileInfo['size'] = \filesize(AIRSHIP_UPLOADS . $fileInfo['realname']);
        } else {
            $fileInfo['size'] = 0;
        }
        if (empty($fileInfo)) {
            throw new FileNotFound();
        }
        return $fileInfo;
    }

    /**
     * @param string $filePath
     * @return string
     */
    public function getMimeType(string $filePath): string
    {
        return $this->finfo->file($filePath);
    }

    /**
     * @param string $name
     * @return bool
     */
    public function isValidName(string $name): bool
    {
        if ($name === '.' || $name === '..') {
            // Web browsers will probably handle relative paths stupidly,
            // so let's avoid the hassle before it ever becomes one
            return false;
        }
        if (
                \strpos($name, '/') !== false
             || \strpos($name, '?') !== false
             || \strpos($name, '&') !== false
        ) {
            // Once again, this is just looking to create a headache down the road.
            return false;
        }
        if (\mb_strlen($name, '8bit') === 1) {
            // Single byte directory/file names must be a printable ASCII character.
            // Preferably: one that is legible and semantically meaningful.
            return 0 < \preg_match('#^[A-Za-z0-9\-_~=\+]$#', $name[0]);
        }
        // No other rules yet.
        return true;
    }

    /**
     * Move/rename a directory
     *
     * @param string $cabin
     * @param string $root
     * @param string $subdirectory
     * @param array $post
     * @return bool
     */
    public function moveDir(
        string $cabin,
        string $root,
        string $subdirectory,
        array $post = []
    ): bool {
        $this->db->beginTransaction();
        // Get the directory IDs...
        $dir = empty($root)
            ? $subdirectory
            : $root . '/' . $subdirectory;
        $nc = empty($root)
            ? $post['new_dir']
            : $root . '/' . $post['new_dir'];
        try {
            if (empty($dir)) {
                $dirId = null;
            } else {
                $dirId = $this->getDirectoryId(
                    \Airship\chunk($dir),
                    $cabin
                );
            }
            if (empty($nc)) {
                $newDir = null;
            } else {
                $newDir = $this->getDirectoryId(
                    \Airship\chunk($nc),
                    $cabin
                );
            }
        } catch (FileNotFound $ex) {
            $this->db->rollBack();
            return false;
        }
        // Grab some facts about the existing directory
        $oldParent = $this->db->cell(
            'SELECT parent FROM airship_dirs WHERE directoryid = ?',
            $dirId
        );

        $dirName = $this->db->cell(
            'SELECT name FROM airship_dirs WHERE directoryid = ?',
            $dirId
        );

        // Did we move directories?
        if ($oldParent !== $newDir) {
            // Detect collisions then update if there are none
            if ($newDir) {
                $exists = $this->db->cell(
                    'SELECT count(*) FROM airship_dirs WHERE parent = ? AND name = ? AND directoryid != ?',
                    $newDir,
                    $post['new_name'],
                    $dirId
                );
                if ($exists > 0) {
                    // There's already a directory here with the same name
                    $this->db->rollBack();
                    return false;
                }
                // Let's move it and, optionally, change the name too
                $this->db->update(
                    'airship_dirs',
                    [
                        'parent' => $newDir,
                        'name' => $post['new_name']
                    ],
                    [
                        'directoryid' => $dirId
                    ]
                );
            } else {
                $exists = $this->db->cell(
                    'SELECT count(*) FROM airship_dirs WHERE parent IS NULL AND cabin = ? AND name = ? AND directoryid != ?',
                    $cabin,
                    $post['new_name'],
                    $dirId
                );
                if ($exists > 0) {
                    // There's already a directory here with the same name
                    $this->db->rollBack();
                    return false;
                }
                // Let's move it and, optionally, change the name too
                $this->db->update(
                    'airship_dirs',
                    [
                        'parent' => null,
                        'name' => $post['new_name']
                    ],
                    [
                        'directoryid' => $dirId
                    ]
                );
            }
        } elseif ($post['new_name'] !== $dirName) {
            // Detect name collisions
            if ($newDir) {
                $exists = $this->db->cell(
                    'SELECT count(*) FROM airship_dirs WHERE parent = ? AND name = ? AND directoryid != ?',
                    $newDir,
                    $post['new_name'],
                    $dirId
                );
            } else {
                $exists = $this->db->cell(
                    'SELECT count(*) FROM airship_dirs WHERE parent IS NULL AND cabin = ? AND name = ? AND directoryid != ?',
                    $cabin,
                    $post['new_name'],
                    $dirId
                );
            }
            if ($exists > 0) {
                // There's already a directory here with the same name
                $this->db->rollBack();
                return false;
            }
            // Change the name
            $this->db->update(
                'airship_dirs',
                [
                    'name' => $post['new_name']
                ],
                [
                    'directoryid' => $dirId
                ]
            );
        } else {
            // Nothing was changed!
            $this->db->rollBack();
            return false;
        }
        return $this->db->commit();
    }

    /**
     * Move/rename a file
     *
     * @param array $fileInfo
     * @param array $post
     * @param string $cabin
     * @return bool
     */
    public function moveFile(array $fileInfo, array $post, string $cabin): bool
    {
        $this->db->beginTransaction();
        // Get the directory IDs...
        $nc = empty($root)
            ? $post['new_dir']
            : $root . '/' . $post['new_dir'];
        try {
            if (empty($nc)) {
                $newDir = null;
            } else {
                $newDir = $this->getDirectoryId(
                    \Airship\chunk($nc),
                    $cabin
                );
            }
        } catch (FileNotFound $ex) {
            $this->db->rollBack();
            return false;
        }

        if ($newDir === $fileInfo['directory'] && $post['new_name'] === $fileInfo['filename']) {
            // NOP
            $this->db->rollBack();
            return false;
        }

        if ($newDir === null) {
            $exists = $this->db->cell(
                'SELECT count(*) FROM airship_files WHERE directory IS NULL AND cabin = ? AND filename = ? AND fileid != ?',
                $cabin,
                $post['new_name'],
                $fileInfo['fileid']
            );
            $update = [
                'directory' => null,
                'cabin' => $cabin,
                'filename' => $post['new_name']
            ];
        } else {
            $exists = $this->db->cell(
                'SELECT count(*) FROM airship_files WHERE directory = ? AND filename = ? AND fileid != ?',
                $newDir,
                $post['new_name'],
                $fileInfo['fileid']
            );
            $update = [
                'directory' => $newDir,
                'filename' => $post['new_name']
            ];
        }
        if ($exists > 0) {
            // There's already a directory here with the same name
            $this->db->rollBack();
            return false;
        }
        $this->db->update(
            'airship_files',
            $update,
            [
                'fileid' => $fileInfo['fileid']
            ]
        );
        return $this->db->commit();
    }

    /**
     * Process an upload. Either it returns an array with useful data, OR it throws an UploadError
     *
     * @param null $directoryId
     * @param string $cabin
     * @param array $file
     * @param array $attribution Who uploaded it?
     * @return array
     * @throws UploadError
     */
    public function processUpload(
        $directoryId = null,
        string $cabin = '',
        array $file = [],
        array $attribution = []
    ): array {
        // First step: Validate our file data
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
            throw new UploadError('File is too large');
            case UPLOAD_ERR_PARTIAL:
                throw new UploadError('Partial file received');
            case UPLOAD_ERR_NO_TMP_DIR:
                throw new UploadError('Temporary directory does not exist');
            case UPLOAD_ERR_CANT_WRITE:
                throw new UploadError('Cannot write to temporary directory');
            case UPLOAD_ERR_OK:
                // Continue
                break;
        }
        if ($file['name'] === '..') {
            throw new UploadError('Invalid file name');
        }
        if (\preg_match('#([^/]+)\.([a-zA-Z0-9]+)$#', $file['name'], $matches)) {
            $name = $matches[1];
            $ext = $matches[2];
        } elseif(\preg_match('#([^/\.]+)$#', $file['name'], $matches)) {
            $name = $matches[1];
            $ext = 'txt';
        } else {
            throw new UploadError('Invalid file name');
        }

        // Actually upload the file.
        $destination = $this->moveUploadedFile($file['tmp_name'], $ext);
        $fullpath = AIRSHIP_UPLOADS . $destination;

        // Get the MIME type and checksum

        $type = $this->getMimeType($fullpath);
        $state = State::instance();
        $checksum = HaliteFile::checksum($fullpath, $state->keyring['cache.hash_key']);

        // Begin transaction
        $this->db->beginTransaction();

        // Get a unique file name
        $filename = $this->getUniqueFileName($name, $ext, $directoryId, $cabin);

        // Insert the new record
        $store = [
            'filename' =>
                $filename,
            'type' =>
                $type,
            'realname' =>
                $destination,
            'checksum' =>
                $checksum,
            'uploaded_by' =>
                $attribution['uploaded_by'] ?? $this->getActiveUserId(),
        ];
        if ($directoryId) {
            $store['directory'] = (int) $directoryId;
        } else {
            $store['cabin'] = (string) $cabin;
        }
        if (!empty($attribution['author'])) {
            $store['author'] = $attribution['author'];
        }
        $newId = $this->db->insertGet('airship_files', $store,'fileid');

        // Did our INSERT query fail?
        if (!$this->db->commit()) {
            // Clean up orphaned file, it was a database error.
            \unlink($fullpath);
            $this->db->rollBack();
            throw new UploadError('A database error occurred trying to save ' . $destination);
        }

        // Return metadata
        return [
            'fileid' => $newId,
            'name' => $filename,
            'type' => $type,
            'csum' => $checksum
        ];
    }

    /**
     * Get a string that doesn't get exist in the DB
     *
     * @param string $name
     * @param string $ext
     * @param int|null $directoryId
     * @param string $cabin
     * @return string
     */
    protected function getUniqueFileName(
        string $name,
        string $ext,
        $directoryId = null,
        string $cabin = ''
    ): string {
        if (empty($directoryId)) {
            $sub = ' directory IS NULL and cabin = ?';
            $subParam = $cabin;
        } else {
            $sub = 'directory = ?';
            $subParam = $directoryId;
        }
        $iterName = $name . '.' . $ext;
        $i = 1;
        while ($this->db->cell(
            'SELECT count(*) FROM airship_files WHERE filename = ? AND '.$sub,
            $iterName,
            $subParam
        ) > 0) {
            $iterName = $name . '-' . ++$i . '.' . $ext;
        }
        return $iterName;
    }

    /**
     * Turn PHP's native $_FILES mess into a sane array
     *
     * USAGE: $result = $this->isolateFiles($_FILES['some_index']);
     *
     * @param array $files
     * @return array
     */
    public function isolateFiles(array $files = []): array
    {
        $isolated = [];
        $numFiles = \count($files['name']);
        for ($i = 0; $i < $numFiles; ++$i) {
            $isolated[$i] = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i]
            ];
        }
        return $isolated;
    }

    /**
     * This method is private to avoid it from being accessed outside of the
     * trusted methods (which handle validation). Don't change it.
     *
     * @param string $tmp_name
     * @param string $ext
     * @return string "HH/HH/HHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHH.ext"
     * @throws UploadError
     */
    private function moveUploadedFile(string $tmp_name, string $ext): string
    {
        if (\in_array(\strtolower($ext), $this->badFileExtensions)) {
            // Just to be extra cautious. The internal filename isn't that important anyway.
            $ext = 'txt';
        }
        $dir1 = \Sodium\bin2hex(\random_bytes(1));
        $dir2 = \Sodium\bin2hex(\random_bytes(1));
        if (!\file_exists(AIRSHIP_UPLOADS . $dir1)) {
            \mkdir(AIRSHIP_UPLOADS . $dir1, 0777);
        }
        if (!\file_exists(AIRSHIP_UPLOADS . $dir1. DIRECTORY_SEPARATOR . $dir2)) {
            \mkdir(AIRSHIP_UPLOADS . $dir1 . DIRECTORY_SEPARATOR . $dir2, 0777);
        }
        $base = AIRSHIP_UPLOADS . $dir1 . DIRECTORY_SEPARATOR . $dir2;
        do {
            $filename = \Sodium\bin2hex(\random_bytes(22)) . '.' . \strtolower($ext);
        } while (\file_exists($base . DIRECTORY_SEPARATOR . $filename));

        if (!\move_uploaded_file($tmp_name, $base . DIRECTORY_SEPARATOR . $filename)) {
            throw new UploadError("Could not move temporary file to its permanent home");
        }
        return $dir1 . DIRECTORY_SEPARATOR .
            $dir2 . DIRECTORY_SEPARATOR .
            $filename;
    }

    /**
     * Recursively delete everything in a specific directory
     *
     * @param int $directory
     * @param string $cabin
     * @return bool
     */
    private function recursiveDelete(int $directory, string $cabin): bool
    {
        foreach ($this->getFilesInDirectory($directory) as $file) {
            $this->deleteFile($file);
        }
        $this->db->beginTransaction();
        foreach ($this->getChildrenOf($directory, $cabin) as $dir) {
            $this->recursiveDelete((int) $dir['directoryid'], $cabin);
        }
        $this->db->delete(
            'airship_dirs',
            [
                'directoryid' => $directory
            ]
        );
        return $this->db->commit();
    }
}
