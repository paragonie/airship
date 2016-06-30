<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Blueprint;

use \Airship\Engine\Bolt\{
    Common,
    Orderable,
    Slug
};

require_once __DIR__.'/init_gear.php';

/**
 * Class Announcements
 * @package Airship\Cabin\Bridge\Blueprint
 */
class Announcements extends BlueprintGear
{
    /**
     * Post a new announcement
     *
     * @param array $post POST data from the Landing
     * @return bool
     */
    public function createAnnouncement(array $post): bool
    {
        $this->db->beginTransaction();

        // We want a unique ID (collision chance 50% at 2^132)
        $query = 'SELECT count(*) FROM bridge_announcements WHERE uniqueid = ?';
        do {
            $unique = \Airship\uniqueId(33);
        } while ($this->db->exists($query, $unique));

        $this->db->insert(
            'bridge_announcements',
            [
                'uniqueid' =>
                    $unique,
                'title' =>
                    $post['title'] ?? '',
                'contents' =>
                    $post['contents'] ?? '',
                'format' =>
                    $post['format'] ?? 'HTML',
                'only_admins' =>
                    !empty($post['only_admins']),

            ]
        );

        return $this->db->commit();
    }

    /**
     * Dismisses an announcement for a particular user.
     *
     * @param int $userID
     * @param string $announceUnique
     * @return bool
     */
    public function dismissForUser(int $userID, string $announceUnique): bool
    {
        $announce = $this->getAnnouncementByUniqueID($announceUnique);
        if (empty($announce)) {
            return false;
        }

        $exists = $this->db->exists(
            'SELECT count(*) FROM bridge_announcements_dismiss WHERE announcementid = ? AND userid = ?',
            $announce['announcementid'],
            $userID
        );
        if ($exists) {
            return true;
        }

        $this->db->beginTransaction();
        $this->db->insert(
            'bridge_announcements_dismiss',
            [
                'announcementid' =>
                    $announce['announcementid'],
                'userid' =>
                    $userID
            ]
        );
        return $this->db->commit();
    }

    /**
     * Get a particular announcement, given its unique ID.
     *
     * @param string $uniqueID
     * @return array
     */
    public function getAnnouncementByUniqueID(string $uniqueID): array
    {
        $result = $this->db->row(
            'SELECT * FROM bridge_announcements WHERE uniqueid = ?',
            $uniqueID
        );
        if (empty($result)) {
            return [];
        }
        return $result;
    }

    /**
     * Get all of the announcements the user has not yet dismissed.
     *
     * @param int $userID
     * @return array
     */
    public function getForUser(int $userID): array
    {
        $query = 'SELECT
                ba.*
            FROM
                bridge_announcements ba
            WHERE
                NOT EXISTS (
                    SELECT 1
                    FROM bridge_announcements_dismiss
                    WHERE userid = ?
                    AND announcementid = ba.announcementid
                )
        ';
        if (!$this->isSuperUser($userID)) {
            $query .= ' AND NOT only_admins';
        }
        $query .= ' ORDER BY created DESC';
        $rows = $this->db->run($query, $userID);
        if (empty($rows)) {
            return [];
        }
        return $rows;
    }
}
