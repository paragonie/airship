<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Landing;

use \Airship\Cabin\Bridge\Blueprint\UserAccounts;
use \Airship\Engine\Bolt\Get;

require_once __DIR__.'/init_gear.php';

/**
 * Class Crew
 * @package Airship\Cabin\Bridge\Landing
 */
class Crew extends AdminOnly
{
    use Get;

    /**
     * @var UserAccounts
     */
    protected $account;

    public function airshipLand()
    {
        parent::airshipLand();

        $this->account = $this->blueprint('UserAccounts');
    }

    /**
     * List the main crew page
     *
     * @route crew
     */
    public function index()
    {
        $this->lens('crew');
    }

    /**
     * Create a new group for users
     *
     * @route crew/groups/new
     */
    public function createGroup()
    {
        $post = $this->post();
        if (!empty($post)) {
            if ($this->account->createGroup($post)) {
                \Airship\redirect($this->airship_cabin_prefix . '/crew/groups');
            }
        }

        $this->lens('crew/group_new', [
            'groups' =>
                $this->account->getGroupTree()
        ]);
    }

    /**
     * Edit a group's information
     *
     * @route crew/groups/edit/{id}
     * @param string $groupId
     */
    public function editGroup(string $groupId = '')
    {
        $groupId += 0;
        $post = $this->post();
        if (!empty($post)) {
            if ($this->account->editGroup($groupId, $post)) {
                \Airship\redirect($this->airship_cabin_prefix . '/crew/groups');
            }
        }

        $this->lens('crew/group_edit', [
            'group' =>
                $this->account->getGroup($groupId),
            'allowed_parents' =>
                $this->account->getGroupTree(0, 'children', [$groupId])
        ]);
    }

    /**
     * Edit a user's information
     *
     * @route crew/users/edit/{id}
     * @param string $userId
     */
    public function editUser(string $userId = '')
    {
        $userId += 0;
        $user = $this->account->getUserAccount($userId, true);
        $post = $this->post();
        if ($post) {
            if ($this->account->editUser($userId, $post)) {
                \Airship\redirect($this->airship_cabin_prefix . '/crew/users');
            }
        }

        $this->lens('crew/user_edit', [
            'user' =>
                $user,
            'groups' =>
                $this->account->getGroupTree()
        ]);
    }

    /**
     * List the groups
     *
     * @route crew/groups
     */
    public function groups()
    {
        $this->lens(
            'crew/group_list',
            [
                'groups' =>
                    $this->account->getGroupTree()
            ]
        );
    }

    /**
     * List the users
     *
     * @route crew/users
     */
    public function users()
    {
        $get = $this->httpGetParams();
        list ($offset, $limit) = $this->getOffsetAndLimit($get['page'] ?? 0);

        $suffix = '';
        $dir = 'ASC';
        if (\array_key_exists('dir', $get)) {
            if ($get['dir'] === 'DESC') {
                $dir = 'DESC';
            }
        }

        if (\array_key_exists('sort', $get)) {
            switch ($get['sort']) {
                case 'username':
                case 'display_name':
                    $suffix = \http_build_query([
                        'sort' => $get['sort'],
                        'dir' => $dir
                    ]) . '&';
                    $users = $this->account->listUsers($offset, $limit, $get['sort'], $dir);
                    break;
                default:
                    $users = $this->account->listUsers($offset, $limit);
            }
        } else {
            $users = $this->account->listUsers($offset, $limit);
        }

        $this->lens('crew/user_list', [
            'users' => $users,
            'pagination' => [
                'base' => $this->airship_cabin_prefix . '/crew/users',
                'suffix' => '?'.$suffix.'page=',
                'count' => $this->account->numUsers(),
                'page' => (int) \ceil($offset / ($limit ?? 1)) + 1,
                'per_page' => $limit
            ]
        ]);
    }

    /**
     * Gets [offset, limit] based on configuration
     *
     * @param string $page
     * @param int $per_page
     * @return int[]
     */
    protected function getOffsetAndLimit($page = null, int $per_page = 50)
    {
        $page = (int) (!empty($page) ? $page : ($_GET['page'] ?? 0));
        if ($page < 1) {
            $page = 1;
        }
        return [($page - 1) * $per_page, $per_page];
    }
}
