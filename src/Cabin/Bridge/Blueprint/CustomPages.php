<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Blueprint;

use Airship\Alerts\Database\QueryError;
use \Airship\Cabin\Hull\Blueprint\CustomPages as HullCustomPages;
use \Airship\Cabin\Hull\Exceptions\{
    CustomPageCollisionException,
    CustomPageNotFoundException
};
use \Airship\Engine\Bolt\Cache;
use \Psr\Log\LogLevel;

/**
 * Class CustomPages
 *
 * Create and manage custom web pages.
 *
 * @package Airship\Cabin\Bridge\Blueprint
 */
class CustomPages extends HullCustomPages
{
    use Cache;

    /**
     * Clears the static page cache
     *
     * @return bool
     * @throws CustomPageNotFoundException
     */
    public function clearPageCache(): bool
    {
        if (\extension_loaded('apcu')) {
            return \apcu_clear_cache();
        }
        foreach (\Airship\list_all_files(ROOT.'/tmp/cache/static') as $f) {
            if (\preg_match('#/([0-9a-z]+)$#', $f)) {
                \unlink($f);
            }
        }
        foreach (\Airship\list_all_files(ROOT.'/tmp/cache/csp_static') as $f) {
            if (\preg_match('#/([0-9a-z]+)$#', $f)) {
                \unlink($f);
            }
        }
        \clearstatcache();
        return true;
    }

    /**
     * Create a new page (and initial page version)
     *
     * @param string $cabin
     * @param string $path
     * @param array $post
     * @return bool
     */
    public function createDir(string $cabin, string $path, array $post = []): bool
    {
        if (empty($post['url'])) {
            return false;
        }
        $this->db->beginTransaction();

        // Get the ID for the parent directory
        if (!empty($path)) {
            $directory_id = $this->getParentDirFromStr($path, $cabin);
            $count = $this->db->cell(
                'SELECT count(*) FROM airship_custom_dir WHERE cabin = ? AND parent = ? AND url = ?',
                $cabin,
                $directory_id,
                $post['url']
            );
        } else {
            $directory_id = null;
            $count = $this->db->cell(
                'SELECT count(*) FROM airship_custom_dir WHERE cabin = ? AND parent IS NULL AND url = ?',
                $cabin,
                $post['url']
            );
        }
        if ($count > 0) {
            // already exists
            return false;
        }
        $this->db->insert(
            'airship_custom_dir',
            [
                'cabin' => $cabin,
                'parent' => $directory_id,
                'url' => $post['url'],
                'active' => true
            ]
        );
        return $this->db->commit();
    }

    /**
     * Create a new page (and initial page version)
     *
     * @param string $cabin
     * @param string $path
     * @param array $post
     * @param bool $publish
     * @param bool $raw
     * @return bool
     */
    public function createPage(
        string $cabin,
        string $path,
        array $post = [],
        bool $publish = false,
        bool $raw = true
    ): bool {
        $this->db->beginTransaction();
        // Get the ID for the parent directory
        if (!empty($path)) {
            $directory_id = $this->getParentDirFromStr($path, $cabin);
        } else {
            $directory_id = null;
        }

        // Create the new page
        $pageId = $this->db->insertGet(
            'airship_custom_page',
            [
                'active' => $publish,
                'cabin' => $cabin,
                'directory' => $directory_id,
                'url' => $post['url'],
                'cache' => !empty($post['cache'])
            ],
            'pageid'
        );

        // Create the first version of the new page
        $this->db->insert(
            'airship_custom_page_version',
            [
                'page' => $pageId,
                'uniqueid' => $this->uniqueId('airship_custom_page_version'),
                'published' => $publish,
                'raw' => $raw,
                'formatting' => $post['format'] ?? 'HTML',
                'bridge_user' => $this->getActiveUserId(),
                'metadata' => !empty($post['metadata'])
                    ? \json_encode($post['metadata'])
                    : '[]',
                'body' => $post['page_body'] ?? ''
            ]
        );
        return $this->db->commit();
    }

    /**
     * Create a redirect
     *
     * @param string $oldURL
     * @param string $newURL
     * @param string $cabin
     * @return bool
     */
    public function createDifferentCabinRedirect(
        string $oldURL,
        string $newURL,
        string $cabin
    ): bool {
        $this->db->beginTransaction();
        try {
            $this->db->insert(
                'airship_custom_redirect',
                [
                    'oldpath' => $oldURL,
                    'newpath' => $newURL,
                    'cabin' => $cabin,
                    'same_cabin' => false
                ]
            );
        } catch (QueryError $e) {
            $this->db->rollBack();
            return false;
        }
        return $this->db->commit();
    }

    /**
     * Create a redirect
     *
     * @param string $oldURL
     * @param string $newURL
     * @param string $cabin
     * @return bool
     */
    public function createSameCabinRedirect(
        string $oldURL,
        string $newURL,
        string $cabin
    ): bool {
        $this->db->beginTransaction();
        try {
            $this->db->insert(
                'airship_custom_redirect',
                [
                    'oldpath' => $oldURL,
                    'newpath' => $newURL,
                    'cabin' => $cabin,
                    'same_cabin' => true
                ]
            );
        } catch (QueryError $e) {
            $this->db->rollBack();
            return false;
        }
        return $this->db->commit();
    }

    /**
     * Redirect a directory, and all of its pages.
     *
     * @todo Implement
     *
     * @param array $old
     * @param array $new
     * @return bool
     */
    public function createDirRedirect(array $old, array $new): bool
    {

    }


    /**
     * Create a page redirect
     *
     * @param array $old
     * @param array $new
     * @return bool
     */
    public function createPageRedirect(array $old, array $new): bool
    {
        if ($old['cabin'] !== $new['cabin']) {
            return false;
        }
        $cabin = $old['cabin'];
        $oldPath = !empty($old['directory'])
            ? $this->getPathFromDirectoryId((int) $old['directory'], $cabin)
            : [];
        $oldPath []= $old['url'];
        $oldURL = \implode('/', $oldPath);
        $newPath = !empty($new['directory'])
            ? $this->getPathFromDirectoryId((int) $new['directory'], $cabin)
            : [];
        $newPath []= $new['url'];
        $newURL = \implode('/', $newPath);

        if ($oldURL === $newURL) {
            return false;
        }
        return $this->createSameCabinRedirect($oldURL, $newURL, $cabin);
    }

    /**
     * Delete a page from the database, along with its revision history.
     *
     * @param int $pageId
     * @return bool
     */
    public function deletePage(int $pageId): bool
    {
        $this->db->beginTransaction();
        $this->db->delete(
            'airship_custom_page_version',
            [
                'page' => $pageId
            ]
        );
        $this->db->delete(
            'airship_custom_page',
            [
                'pageid' => $pageId
            ]
        );
        return $this->db->commit();
    }

    /**
     * Delete a redirect.
     *
     * @param int $redirectID
     * @return bool
     */
    public function deleteRedirect(int $redirectID): bool
    {
        $this->db->beginTransaction();
        $this->db->delete(
            'airship_custom_redirect',
            [
                'redirectid' => $redirectID
            ]
        );
        return $this->db->commit();
    }

    /**
     * Get a custom directory tree
     *
     * @param array $cabins
     * @param int $selected
     * @return array
     */
    public function getCustomDirTree(array $cabins, int $selected): array
    {
        $tree = [];
        foreach ($cabins as $cabin) {
            $tree[$cabin] = $this->getCustomDirChildren($cabin, 0, $selected);
        }
        return $tree;
    }

    /**
     * Get the cabin assigned to a particular directory
     *
     * @param int $directoryId
     * @return string
     */
    public function getCabinForDirectory(int $directoryId): string
    {
        $cabin = $this->db->cell(
            'SELECT cabin FROM airship_custom_dir WHERE directoryid = ?',
            $directoryId
        );
        if (empty($cabin)) {
            return '';
        }
        return $cabin;
    }

    /**
     * Recursively grab the children of the custom directory tree
     *
     * @param string $cabin
     * @param int $directoryId
     * @param int $selected
     * @param int $depth
     * @return array
     * @throws CustomPageNotFoundException
     */
    public function getCustomDirChildren(
        string $cabin,
        int $directoryId = 0,
        int $selected = 0,
        int $depth = 0
    ): array {
        $level = [];
        if ($directoryId === 0) {
            $branches = $this->db->run(
                'SELECT * FROM airship_custom_dir WHERE cabin = ? AND parent IS NULL',
                $cabin
            );
            if (empty($branches)) {
                return [];
            }
        } else {
            $branches = $this->db->run(
                'SELECT * FROM airship_custom_dir WHERE parent = ?',
                $directoryId
            );
            if (empty($branches)) {
                throw new CustomPageNotFoundException('No branches');
            }
        }
        // Now let's recurse:
        foreach ($branches as $br) {
            $br['selected'] = $br['directoryid'] === $selected;
            try {
                $br['children'] = $this->getCustomDirChildren(
                    $cabin,
                    (int) $br['directoryid'],
                    $selected,
                    $depth + 1
                );
            } catch (CustomPageNotFoundException $e) {
                $br['children'] = null;
            }
            $level[] = $br;
        }
        return $level;
    }

    /**
     * Get all of a page's revision history
     *
     * @param int $pageId
     * @return array
     */
    public function getHistory(int $pageId): array
    {
        return $this->db->run(
            'SELECT
                *
            FROM
                airship_custom_page_version
            WHERE
                    page = ?
                ORDER BY versionid DESC
            ',
            $pageId
        );
    }
    /**
     * Get the latest published version ID of a custom page
     *
     * @param int $pageId
     * @return int
     */
    public function getLatestVersionId(int $pageId): int
    {
        $latest = $this->db->cell(
            'SELECT
                versionid
            FROM
                airship_custom_page_version
            WHERE
                    page = ?
                AND published
                ORDER BY versionid DESC
                LIMIT 1
            ',
            $pageId
        );
        if (empty($latest)) {
            return 0;
        }
        return $latest;
    }

    /**
     * Get the latest version of a custom page
     *
     * @param int $pageId
     * @return array
     * @throws CustomPageNotFoundException
     */
    public function getLatestDraft(int $pageId): array
    {
        $latest = $this->db->row(
            'SELECT
                *
            FROM
                airship_custom_page_version
            WHERE
                    page = ?
                ORDER BY versionid DESC
                LIMIT 1
            ',
            $pageId
        );
        if (empty($latest)) {
            return [];
        }
        if (!empty($latest['metadata'])) {
            $latest['metadata'] = \json_decode($latest['metadata'], true);
        } else {
            $latest['metadata'] = [];
        }
        return $latest;
    }

    /**
     * Get information about only the page
     *
     * @param string $cabin
     * @param string $path
     * @param string $page
     * @return array
     * @throws CustomPageNotFoundException
     */
    public function getPageInfo(string $cabin, string $path = '', string $page): array
    {
        if ($path === '') {
            $directory = 0;
        } else {
            $directory = $this->getParentDirFromStr($path, $cabin);
        }
        return $this->getPage($page, $directory, $cabin);
    }

    /**
     * Get path information by a page ID
     *
     * @param int $pageId
     * @param string $cabin
     * @return array string[3] (cabin, directory, page)
     * @throws CustomPageNotFoundException
     */
    public function getPathByPageId(int $pageId, string $cabin = ''): array
    {
        $page = $this->db->row(
            'SELECT url, directory FROM airship_custom_page WHERE pageid = ?',
            $pageId
        );
        if (empty($page)) {
            throw new CustomPageNotFoundException(
                \trk('errors.pages.page_does_not_exist')
            );
        }
        if (empty($page['directory'])) {
            return [];
        }
        try {
            $path = \implode('/',
                $this->getPathFromDirectoryId(
                    (int) $page['directory'],
                    $cabin
                )
            );
            return \trim($path, '/') . '/' . $page['url'];
        } catch (CustomPageNotFoundException $ex) {
            $this->log(
                'Custom directory not found',
                LogLevel::ALERT,
                [
                    'pageid' => $pageId,
                    'url' => $page['url'],
                    'directory' => $page['directory'],
                    'cabin' => $cabin,
                    'exception' => \Airship\throwableToArray($ex)
                ]
            );
            throw $ex;
        }
    }

    /**
     * Get the information about a particular page version, given a unique ID
     *
     * @param string $uniqueId
     * @return array
     * @throws CustomPageNotFoundException
     */
    public function getPageVersionByUniqueId(string $uniqueId): array
    {
        $row = $this->db->row(
            'SELECT * FROM airship_custom_page_version WHERE uniqueid = ?',
            $uniqueId
        );
        if ($row) {
            return $row;
        }
        throw new CustomPageNotFoundException(
            \trk('errors.page.unknown_version')
        );
    }

    /**
     * Get the next version's unique ID
     *
     * @param int $pageId
     * @param int $currentVersionId
     * @return string
     */
    public function getPrevVersionUniqueId(int $pageId, int $currentVersionId): string
    {
        $latest = $this->db->cell(
            'SELECT
                uniqueid
            FROM
                airship_custom_page_version
            WHERE
                    page = ?
                AND versionid < ?
                AND published
                ORDER BY versionid DESC
                LIMIT 1
            ',
            $pageId,
            $currentVersionId
        );
        if (empty($latest)) {
            return '';
        }
        return $latest;
    }

    /**
     * Get the next version's unique ID
     *
     * @param int $pageId
     * @param int $currentVersionId
     * @return string
     */
    public function getNextVersionUniqueId(int $pageId, int $currentVersionId): string
    {
        $latest = $this->db->cell(
            'SELECT
                uniqueid
            FROM
                airship_custom_page_version
            WHERE
                    page = ?
                AND versionid > ?
                AND published
                ORDER BY versionid DESC
                LIMIT 1
            ',
            $pageId,
            $currentVersionId
        );
        if (empty($latest)) {
            return '';
        }
        return $latest;
    }

    /**
     * Get the redirects for this particular cabin.
     *
     * @param string $cabin
     * @param int $redirectID
     * @return array
     */
    public function getRedirect(string $cabin, int $redirectID): array
    {
        $redirect = $this->db->row(
            'SELECT * FROM airship_custom_redirect WHERE cabin = ? AND redirectid = ? ORDER BY oldpath ASC',
            $cabin,
            $redirectID
        );
        if (empty($redirect)) {
            return [];
        }
        return $redirect;
    }

    /**
     * Get the redirects for this particular cabin.
     *
     * @param string $cabin
     * @return array
     */
    public function getRedirectsForCabin(string $cabin): array
    {
        $redirects = $this->db->run(
            'SELECT * FROM airship_custom_redirect WHERE cabin = ? ORDER BY oldpath ASC',
            $cabin
        );
        if (empty($redirects)) {
            return [];
        }
        return $redirects;
    }

    /**
     * List all of the custom pages contained within a given cabin and directory
     *
     * @param string $dir
     * @param string $cabin
     * @return array
     */
    public function listCustomPages(string $dir = '', string $cabin = \CABIN_NAME): array
    {
        if (empty($dir)) {
            return $this->db->run(
                'SELECT * FROM airship_custom_page WHERE directory IS NULL AND cabin = ?',
                $cabin
            );
        }
        $parent = $this->getParentDirFromStr($dir);
        return $this->db->run(
            'SELECT * FROM airship_custom_page WHERE directory = ?',
            $parent
        );
    }

    /**
     * List all of the subdirectories for a given cabin and directory
     *
     * @param string $dir
     * @param string $cabin
     * @return array
     */
    public function listSubDirectories(string $dir = '', string $cabin = \CABIN_NAME): array
    {
        if (empty($dir)) {
            return $this->db->run(
                'SELECT * FROM airship_custom_dir WHERE parent IS NULL AND cabin = ?',
                $cabin
            );
        }
        $parent = $this->getParentDirFromStr($dir);
        return $this->db->run(
            'SELECT * FROM airship_custom_dir WHERE parent = ? AND cabin = ?',
            $parent,
            $cabin
        );
    }

    /**
     * Move a page to a new directory
     *
     * @param int $pageId the page we're changing
     * @param string $url the new URL
     * @param int $destinationDir the new directory
     * @return bool
     * @throws CustomPageCollisionException
     */
    public function movePage(int $pageId, string $url = '', int $destinationDir = 0): bool
    {
        $this->db->beginTransaction();
        if ($destinationDir > 0) {
            $collision = $this->db->cell(
                'SELECT COUNT(pageid) FROM airship_custom_page WHERE directory = ? AND url = ? AND pageid != ?',
                $destinationDir,
                $url,
                $pageId
            );
        } else {
            $collision = $this->db->cell(
                'SELECT COUNT(pageid) FROM airship_custom_page WHERE directory IS NULL AND url = ? AND pageid != ?',
                $url,
                $pageId
            );
        }
        if ($collision > 0) {
            // Sorry, but no.
            throw new CustomPageCollisionException(
                \trk('errors.pages.collision')
            );
        }
        $this->db->update(
            'airship_custom_page',
            [
                'url' => $url,
                'directory' => $destinationDir > 0
                    ? $destinationDir
                    : null
            ],
            [
                'pageid' => $pageId
            ]
        );
        return $this->db->commit();
    }

    /**
     * Get the number of custom pages.
     *
     * @param null $published
     * @return int
     */
    public function numCustomPages($published = null): int
    {
        if ($published === null) {
            return (int) $this->db->cell('SELECT count(pageid) FROM airship_custom_page');
        }
        if ($published) {
            return (int) $this->db->cell('SELECT count(pageid) FROM airship_custom_page WHERE active');
        }
        return (int) $this->db->cell('SELECT count(pageid) FROM airship_custom_page WHERE NOT active');
    }

    /**
     * Create a new page (and initial page version)
     *
     * @param int $pageId
     * @param array $post
     * @param bool $publish (This is set to FALSE by the Landing if the permission check fails)
     * @param bool|null $raw
     * @param bool $cache
     * @return bool
     */
    public function updatePage(
        int $pageId,
        array $post = [],
        bool $publish = false,
        $raw = null,
        bool $cache = false
    ): bool {
        $this->db->beginTransaction();
        if ($publish) {
            $this->db->update(
                'airship_custom_page',
                [
                    'active' => true,
                    'cache' => $cache
                ],
                [
                    'pageid' => $pageId
                ]
            );
        }

        if ($raw === null) {
            $raw = $this->db->cell(
                'SELECT raw FROM airship_custom_page_version WHERE page = ? ORDER BY versionid DESC LIMIT 1',
                $pageId
            );
        }

        $last_copy = $this->db->row(
            'SELECT * FROM airship_custom_page_version WHERE page = ? ORDER BY versionid DESC LIMIT 1',
            $pageId
        );

        // Short circuit the needless inserts
        if (
            $last_copy['raw'] === (bool) $raw &&
            $last_copy['formatting'] === (string) $post['format'] &&
            \json_decode($last_copy['metadata'], true) === $post['metadata'] &&
            $last_copy['body'] === (string) $post['page_body']
        ) {
            // Are we publishing a previously unpublished edition?
            if ($publish && !$last_copy['publish']) {
                $this->db->update(
                    'airship_custom_page_version',
                    [
                        'published' => $publish
                    ],
                    [
                        'page' => $pageId
                    ]
                );
            }
            $this->clearPageCache();
            return $this->db->commit();
        }
        $meta = !empty($post['metadata'])
            ? \json_encode($post['metadata'])
            : '[]';

        // Create the next version of the new page
        $this->db->insert(
            'airship_custom_page_version',
            [
                'page' => (int) $pageId,
                'published' => (bool) $publish,
                'uniqueid' => $this->uniqueId('airship_custom_page_version'),
                'raw' => (bool) $raw,
                'formatting' => (string) $post['format'] ?? 'HTML',
                'bridge_user' => (int) $this->getActiveUserId(),
                'metadata' => (string) $meta,
                'body' => (string) $post['page_body'] ?? ''
            ]
        );
        if ($publish) {
            // Nuke the page cache
            $this->clearPageCache();
        }
        return $this->db->commit();
    }

    /**
     * @param int $redirectID
     * @param array $post
     * @return bool
     */
    public function updateRedirect(int $redirectID, array $post): bool
    {
        $this->db->beginTransaction();
        $sameCabin = !\preg_match('#^https?://#', $post['new_url']);
        try {
            $this->db->update(
                'airship_custom_redirect',
                [
                    'oldpath' => $post['old_url'],
                    'newpath' => $post['new_url'],
                    'same_cabin' => !$sameCabin
                ],
                [
                    'redirectid' => $redirectID
                ]
            );
        } catch (QueryError $e) {
            $this->db->rollBack();
            return false;
        }
        return $this->db->commit();
    }

    /**
     * Get a unique ID (and make sure it doesn't exist)
     *
     * @param string $table
     * @param string $column
     * @return string
     */
    protected function uniqueId(string $table, string $column = 'uniqueid'): string
    {
        do {
            $unique = \Airship\uniqueId();
        } while (
            $this->db->cell(
                'SELECT count(*) FROM ' .
                    $this->db->escapeIdentifier($table) .
                    ' WHERE '.$this->db->escapeIdentifier($column).' = ?',
                $unique
            ) > 0
        );
        return $unique;
    }
}
