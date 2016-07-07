<?php
declare(strict_types=1);
namespace Airship\Cabin\Hull\Blueprint;

use Airship\Alerts\FileSystem\FileNotFound;

require_once __DIR__.'/init_gear.php';

/**
 * Class PublicFiles
 *
 * Read-only access to the virtual filesystem
 *
 * @package Airship\Cabin\Hull\Blueprint
 */
class PublicFiles extends BlueprintGear
{
    /**
     * Get a virtual directory ID
     *
     * @param string $cabin
     * @param array $parts
     * @return int
     * @throws FileNotFound
     */
    public function getDirectoryId(string $cabin, array $parts): int
    {
        $directoryId = null;
        do {
            $part = \array_shift($parts);
            if (empty($directoryId)) {
                $directoryId = $this->db->cell(
                    'SELECT
                         directoryid
                     FROM
                         airship_dirs
                     WHERE
                            cabin = ?
                         AND parent IS NULL
                         AND name = ?
                     ',
                    $cabin,
                    $part
                );
            } else {
                $directoryId = $this->db->cell(
                    'SELECT
                         directoryid
                     FROM
                         airship_dirs
                     WHERE
                             parent = ?
                         AND name = ?
                     ',
                    $directoryId,
                    $part
                );
            }
            if (empty($directoryId)) {
                throw new FileNotFound();
            }
        } while (!empty($parts));
        return $directoryId;
    }

    /**
     * Get information about a file
     *
     * @param string $cabin
     * @param array $path
     * @param string $filename
     * @return array
     * @throws FileNotFound
     */
    public function getFileInfo(
        string $cabin = '',
        array $path = [],
        string $filename = ''
    ): array {
        if (empty($path)) {
            $fileInfo = $this->db->row(
                'SELECT
                     *
                 FROM
                     airship_files
                 WHERE
                         directory IS NULL
                     AND cabin = ?
                     AND filename = ?
                 ',
                $cabin,
                $filename
            );
        } else {
            $fileInfo = $this->db->row(
                'SELECT
                     *
                 FROM
                     airship_files
                 WHERE
                         directory = ?
                     AND filename = ?
                 ',
                $this->getDirectoryId($cabin, $path),
                $filename
            );
        }
        if (empty($fileInfo)) {
            throw new FileNotFound();
        }
        // Only printable ASCII characters are permitted in this header:
        $fileInfo['type'] = \preg_replace('#[^\x20-\x7e/]#', '', $fileInfo['type']);
        return $fileInfo;
    }
}
