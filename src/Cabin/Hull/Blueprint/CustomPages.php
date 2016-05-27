<?php
declare(strict_types=1);
namespace Airship\Cabin\Hull\Blueprint;

use \Airship\Cabin\Hull\Exceptions\CustomPageNotFoundException;

require_once __DIR__.'/init_gear.php';

/**
 * Class CustomPages
 *
 * Custom web pages
 *
 * @package Airship\Cabin\Hull\Blueprint
 */
class CustomPages extends BlueprintGear
{
    protected $cabin;

    /**
     * Set the cabin for this gear
     *
     * @param string $cabin
     * @return CustomPages
     */
    public function setCabin(string $cabin): CustomPages
    {
        $this->cabin = $cabin;
        return $this;
    }

    /**
     * Get all of the parent directories' URL components
     *
     * @param int $directoryId
     * @param string $cabin
     * @param array $idsEncountered Prevent infinite loops
     * @return string[]
     * @throws CustomPageNotFoundException
     */
    public function getPathFromDirectoryId(
        int $directoryId,
        string $cabin = '',
        array $idsEncountered = []
    ): array {
        // Let's grab the current row
        $row = $this->db->row(
            'SELECT
                url,
                parent,
                cabin
            FROM
                airship_custom_dir
            WHERE
                  directoryid = ?
              AND parent NOT IN '.$this->db->escapeValueSet($idsEncountered, 'int'),
            $directoryId
        );
        if (empty($row)) {
            // This _shouldn't_ be triggered.
            return [];
        }

        // Did we specify a cabin?
        if (!empty($cabin)) {
            // Did it match this row's cabin?
            if ($row['cabin'] !== $cabin) {
                throw new CustomPageNotFoundException(
                    \trk('errors.pages.directory_wrong_cabin', $row['url'], $cabin, $row['cabin'])
                );
            }
        }

        // If we have no parent, just return this row's URL piece. We're done.
        if (empty($row['parent'])) {
            return [ $row['url'] ];
        }

        // Prevent infinite loops:
        if (\in_array($row['parent'], $idsEncountered)) {
            return [ $row['url'] ];
        }

        // Append the current ID to the encountered IDs list
        $idsEncountered[] = $directoryId;
        $pieces = $this->getPathFromDirectoryId($row['parent'], $row['cabin'], $idsEncountered);
        $pieces[] = $row['url'];
        return $pieces;
    }

    /**
     * Get the directory ID for a given path
     *
     * @param string $dir
     * @param int $parent
     * @param string $cabin
     * @return int
     * @throws CustomPageNotFoundException
     */
    public function getDirectoryId(string $dir, int $parent = 0, string $cabin = ''): int
    {
        if (empty($cabin)) {
            $cabin = $this->cabin;
        }
        if (empty($parent)) {
            $res = $this->db->cell(
                "SELECT
                     directoryid
                 FROM
                     airship_custom_dir
                 WHERE
                         active
                     AND cabin = ?
                     AND url = ?
                     AND parent IS NULL
                ",
                $cabin,
                $dir
            );
        } else {
            $res = $this->db->cell(
                "SELECT
                 directoryid
             FROM
                 airship_custom_dir
             WHERE
                     active
                 AND cabin = ?
                 AND url = ?
                 AND parent = ?
            ",
                $cabin,
                $dir,
                $parent
            );
        }
        if ($res === false) {
            throw new CustomPageNotFoundException(
                \trk('errors.pages.directory_does_not_exist')
            );
        }
        return (int) $res;
    }

    /**
     * Get the parent directory
     *
     * @param string $dir
     * @param string $cabin
     * @return int
     */
    public function getParentDirFromStr(string $dir, string $cabin = ''): int
    {
        return $this->getParentDir(
            \Airship\chunk($dir),
            $cabin
        );
    }

    /**
     * Get the parent directory
     *
     * @param array $dirs
     * @param string $cabin
     * @return int
     */
    public function getParentDir(
        array $dirs = [],
        string $cabin = ''
    ): int {
        $parent = 0;
        foreach ($dirs as $dir) {
            $parent = $this->getDirectoryId($dir, $parent, $cabin);
        }
        return $parent;
    }

    /**
     * Get information about a custom page.
     *
     * @param string $file
     * @param int $directoryId
     * @param string $cabin
     * @return array
     * @throws CustomPageNotFoundException
     */
    public function getPage(
        string $file,
        int $directoryId = 0,
        string $cabin = ''
    ): array {
        if (empty($cabin)) {
            $cabin = $this->cabin;
        }
        if ($directoryId > 0) {
            $page = $this->db->row(
                "SELECT
                    *
                FROM
                    airship_custom_page
                WHERE
                        active
                    AND cabin = ?
                    AND directory = ?
                    AND url = ?
                ",
                $cabin,
                $directoryId,
                $file
            );
        } else {
            // No directory? Only look for when it's null then!
            $page = $this->db->row(
                "SELECT
                    *
                FROM
                    airship_custom_page
                WHERE
                        active
                    AND cabin = ?
                    AND directory IS NULL
                    AND url = ?
                ",
                $cabin,
                $file
            );
        }
        if (empty($page)) {
            throw new CustomPageNotFoundException();
        }
        return $page;
    }
    /**
     * Get information about a custom page.
     *
     * @param int $pageId
     * @return array
     * @throws CustomPageNotFoundException
     */
    public function getPageById(int $pageId): array
    {
        $page = $this->db->row(
            "SELECT
                *
            FROM
                airship_custom_page
            WHERE
                pageid = ?
            ",
            $pageId
        );
        if (empty($page)) {
            throw new CustomPageNotFoundException();
        }
        return $page;
    }

    /**
     * Get the latest version of a custom page
     *
     * @param int $pageId
     * @return array
     * @throws CustomPageNotFoundException
     */
    public function getLatestVersion(int $pageId): array
    {
        $latest = $this->db->row(
            "SELECT
                *
            FROM
                airship_custom_page_version
            WHERE
                    published
                AND page = ?
                ORDER BY versionid DESC
                LIMIT 1
            ",
            $pageId
        );
        if (empty($latest)) {
            throw new CustomPageNotFoundException("Page ID: ".$pageId);
        }
        if (!empty($latest['metadata'])) {
            $latest['metadata'] = \json_decode($latest['metadata'], true);
        } else {
            $latest['metadata'] = [];
        }
        return $latest;
    }

    /**
     * If a redirect exists at this path, serve it.
     *
     * @param string $uri
     * @return bool
     */
    public function serveRedirect(string $uri): bool
    {
        $lookup = $this->db->row(
            'SELECT * FROM airship_custom_redirect WHERE oldpath = ?',
            $uri
        );
        if (empty($lookup)) {
            return false;
        }
        \Airship\redirect($lookup['newpath']); // Exits
        return true;
    }
}
