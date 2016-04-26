<?php
declare(strict_types=1);
namespace Airship\Engine\Security;

use \Airship\Engine\{
    AutoPilot,
    Database,
    State,
    Contract\DBInterface
};
use \Airship\Alerts\Database\NotImplementedException;

/**
 * Class Permissions
 *
 * Manages user-based and role-based access controls with overlapping
 * pattern-based contexts and a multi-site architecture, with a simple
 * interface. i.e. $this->can('read') // bool(false)
 *
 * @package Airship\Engine\Security
 */
class Permissions
{
    const MAX_RECURSE_DEPTH = 100;
    
    private $db;
    
    /**
     * @param DBInterface $db
     */
    public function __construct(DBInterface $db)
    {
        $this->db = $db;
        if (IDE_HACKS) {
            define('CABIN_NAME', '');
            $this->db = new Database(new \PDO('sqlite::memory:', 'sqlite'));
        }
    }

    /**
     * Perform a permissions check
     *
     * @param string $action action label (e.g. 'read')
     * @param string $context_path context regex (in perm_contexts)
     * @param string $cabin (defaults to current cabin)
     * @param integer $user_id (defaults to current user)
     * @return boolean
     */
    public function can(
        string $action,
        string $context_path = '',
        string $cabin = \CABIN_NAME,
        int $user_id = 0
    ): bool {
        $state = State::instance();
        if (empty($cabin)) {
            $cabin = \CABIN_NAME;
        }
        // If you don't specify the user ID to check, it will use the current
        // user ID instead, by default.
        if (empty($user_id)) {
            $idx = isset($state->config['session_index']['user_id'])
                ? $state->config['session_index']['user_id']
                : 'userid';
            if (!empty($_SESSION[$idx])) {
                $user_id = $_SESSION[$idx];
            }
        }

        // If you're a super-user, the answer is "yes, yes you can".
        if ($this->isSuperUser($user_id)) {
            return true;
        }
        $allowed = false;
        $failed_one = false;
        
        // Get all applicable contexts
        $contexts = self::getOverlap($context_path, $cabin);
        if (empty($contexts)) {
            // Sane default: In the absence of permissions, return false
            return false;
        }
        if ($user_id > 0) {
            // You need to be allowed in every relevant context.
            foreach ($contexts as $c_id) {
                if (
                    self::checkUser($action, $c_id, $user_id) ||
                    self::checkUsersGroups($action, $c_id, $user_id)
                ) {
                    $allowed = true;
                } else {
                    $failed_one = true;
                }
            }
        } else {
            // Guests can be assigned to groups. This fails closed if they aren't given any.
            foreach ($contexts as $c_id) {
                $ctx_res = false;
                foreach ($state->universal['guest_groups'] as $grp) {
                    if (self::checkGroup($action, $c_id, $grp)) {
                        $ctx_res = true;
                    }
                }
                if ($ctx_res) {
                    $allowed = true;
                } else {
                    $failed_one = true;
                }
            }
        }
        // We return true if we were allowed at least once and we did not fail
        // in one of the overlapping contexts
        return $allowed && !$failed_one;
    }

    /**
     * Do the members of this group have permission to do something?
     *
     * @param string $action - perm_actions.label
     * @param int $context_id - perm_contexts.contextid
     * @param integer $group_id - groups.groupid
     * @param bool $deep_search - Also search groups' inheritances
     * @return bool
     */
    public function checkGroup(
        string $action,
        int $context_id = null,
        int $group_id = null,
        bool $deep_search = true
    ): bool {
        return 0 < $this->db->single(
            \Airship\queryStringRoot(
                $deep_search
                    ? 'security.permissions.check_groups_deep'
                    : 'security.permissions.check_groups',
                $this->db->getDriver()
            ),
            [
                'action' => $action,
                'context' => $context_id,
                'group' => $group_id
            ]
        );
    }

    /**
     * Check that the user, specifically, has permission to do something.
     * Ignores group-based access controls.
     *
     * @param string $action
     * @param int|null $context_id
     * @param int|null $user_id
     * @param bool $ignore_superuser
     * @return bool
     * @throws NotImplementedException
     */
    public function checkUser(
        string $action,
        int $context_id = null,
        int $user_id = null,
        bool $ignore_superuser = false
    ): bool {
        if (!$ignore_superuser) {
            if ($this->isSuperUser($user_id)) {
                return true;
            }
        }
        $check = $this->db->single(
            \Airship\queryStringRoot(
                'security.permissions.check_user',
                $this->db->getDriver()
            ),
            [
                'action' => $action,
                'context' => $context_id,
                'user' => $user_id
            ]
        );
        return $check > 0;
    }

    /**
     * Check that any of the users' groups has the permission bit
     *
     * @param string $action
     * @param int|null $context_id
     * @param int|null $user_id
     * @param bool $ignore_superuser
     * @return bool
     */
    public function checkUsersGroups(
        string $action = '',
        int $context_id = null,
        int $user_id = null,
        bool $ignore_superuser = false
    ): bool {
        if (!$ignore_superuser) {
            if ($this->isSuperUser($user_id)) {
                return true;
            }
        }
        return 0 < $this->db->single(
            \Airship\queryStringRoot(
                'security.permissions.check_users_groups',
                $this->db->getDriver()
            ),
            [
                'action' => $action,
                'context' => $context_id,
                'user' => $user_id
            ]
        );
    }

    /**
     * Returns an array with overlapping context IDs -- useful for when
     * contexts are used with regular expressions
     *
     * @param string $context Context
     * @param string $cabin Cabin
     * @return array
     */
    public function getOverlap(
        string $context = '',
        string $cabin = \CABIN_NAME
    ): array {
        if (empty($context)) {
            $context = AutoPilot::$path;
        }
        $ctx = $this->db->first(
            \Airship\queryStringRoot(
                'security.permissions.get_overlap',
                $this->db->getDriver()
            ),
            $cabin,
            $context
        );
        if (empty($ctx)) {
            return [];
        }
        return $ctx;
    }
    
    /**
     * Is this user a super user? Do they belong in a superuser group?
     * 
     * @param int $user_id - User ID
     * @param bool $ignore_groups - Don't look at their groups
     * @return bool
     */
    public function isSuperUser(
        int $user_id = 0,
        bool $ignore_groups = false
    ): bool {
        if (empty($user_id)) {
            // We can short-circuit this for guests...
            return false;
        }

        $statements = [
            'check_user' => \Airship\queryStringRoot(
                'security.permissions.is_superuser_user',
                $this->db->getDriver()
            ),
            'check_groups' =>\Airship\queryStringRoot(
                'security.permissions.is_superuser_group',
                $this->db->getDriver()
            )
        ];

        if ($this->db->cell($statements['check_user'], $user_id) > 0) {
            return true;
        } elseif (!$ignore_groups) {
            return $this->db->cell($statements['check_groups'], $user_id) > 0; 
        }
        return false;
    }
}
