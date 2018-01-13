<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Controller;

use Airship\Alerts\CabinNotFound;
use Airship\Cabin\Bridge\Model\{
    Announcements,
    Author,
    Blog,
    Files,
    Permissions,
    UserAccounts
};

require_once __DIR__.'/init_gear.php';

/**
 * Class Ajax
 * @package Airship\Cabin\Bridge\Controller
 */
class Ajax extends LoggedInUsersOnly
{
    /**
     * @route announcement/dismiss
     * @throws \TypeError
     */
    public function dismissAnnouncement(): void
    {
        $announce_model = $this->model('Announcements');
        if (!($announce_model instanceof Announcements)) {
            throw new \TypeError(
                \trk('errors.type.wrong_class', Announcements::class)
            );
        }
        $post = $this->ajaxPost($_POST, 'csrf_token');
        if (empty($post['dismiss'])) {
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => 'Insufficient parameters'
            ]);
        }
        $result = $announce_model->dismissForUser(
            $this->getActiveUserId(),
            $post['dismiss']
        );
        if ($result) {
            \Airship\json_response([
                'status' => 'OK',
                'message' => ''
            ]);
        }
        \Airship\json_response([
            'status' => 'ERROR',
            'message' => 'An unknown error has occurred.'
        ]);
    }

    /**
     * @route ajax/authors_get_photo
     * @throws \TypeError
     */
    public function getAuthorsPhoto(): void
    {
        $auth_model = $this->model('Author');
        if (!($auth_model instanceof Author)) {
            throw new \TypeError(
                \trk('errors.type.wrong_class', Author::class)
            );
        }
        $file_model = $this->model('Files');
        if (!($file_model instanceof Files)) {
            throw new \TypeError(
                \trk('errors.type.wrong_class', Files::class)
            );
        }
        $post = $this->ajaxPost($_POST, 'csrf_token');
        $authorId = (int) ($post['author'] ?? 0);
        if (!$this->isSuperUser()) {
            $authors = $auth_model->getAuthorIdsForUser(
                $this->getActiveUserId()
            );
            if (!\in_array($authorId, $authors)) {
                \Airship\json_response([
                    'status' => 'ERROR',
                    'message' => \__('You do not have permission to access this author\'s posts.')
                ]);
            }
        }
        if (!\Airship\all_keys_exist(['context', 'author'], $post)) {
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => 'Insufficient parameters'
            ]);
        }
        $file = $auth_model->getPhotoData($authorId, $post['context']);
        if (empty($file)) {
            // No file selected
            \Airship\json_response([
                'status' => 'OK',
                'message' => '',
                'photo' => null
            ]);
        }
        $cabin = $file_model->getFilesCabin((int) $file['fileid']);
        \Airship\json_response([
            'status' => 'OK',
            'message' => '',
            'photo' =>
                \Airship\ViewFunctions\cabin_url($cabin) .
                    'files/author/' .
                    $file['slug'] . '/' .
                    $auth_model->getPhotoDirName() . '/' .
                    $file['filename']
        ]);
    }

    /**
     * @route ajax/authors_photo_available
     * @throws \TypeError
     */
    public function getAuthorsAvailablePhotos(): void
    {
        $auth_model = $this->model('Author');
        if (!($auth_model instanceof Author)) {
            throw new \TypeError(
                \trk('errors.type.wrong_class', Author::class)
            );
        }

        $post = $this->ajaxPost($_POST, 'csrf_token');
        $authorId = (int) ($post['author'] ?? 0);
        if (!$this->isSuperUser()) {
            $authors = $auth_model->getAuthorIdsForUser(
                $this->getActiveUserId()
            );
            if (!\in_array($authorId, $authors)) {
                \Airship\json_response([
                    'status' => 'ERROR',
                    'message' => \__('You do not have permission to access this author\'s posts.')
                ]);
            }
        }

        if (empty($post['cabin']) || !$authorId) {
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => 'Insufficient parameters'
            ]);
        }

        \Airship\json_response([
            'status' => 'OK',
            'message' => '',
            'photos' => $auth_model->getAvailablePhotos($authorId, $post['cabin'])
        ]);
    }

    /**
     * @route ajax/authors_blog_posts
     * @throws \TypeError
     */
    public function getBlogPostsForAuthor(): void
    {
        $auth_model = $this->model('Author');
        if (!($auth_model instanceof Author)) {
            throw new \TypeError(
                \trk('errors.type.wrong_class', Author::class)
            );
        }
        $blog_model = $this->model('Blog');
        if (!($blog_model instanceof Blog)) {
            throw new \TypeError(
                \trk('errors.type.wrong_class', Blog::class)
            );
        }

        $post = $this->ajaxPost($_POST, 'csrf_token');
        if (empty($post['author'])) {
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => \__('No author selected.')
            ]);
        }
        $authorId = (int) ($post['author'] ?? 0);
        if (!$this->isSuperUser()) {
            $authors = $auth_model->getAuthorIdsForUser(
                $this->getActiveUserId()
            );
            if (!\in_array($authorId, $authors)) {
                \Airship\json_response([
                    'status' => 'ERROR',
                    'message' => \__('You do not have permission to access this author\'s posts.')
                ]);
            }
        }
        $existing = $post['existing'] ?? [];
        if (!\is1DArray($existing)) {
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => \__('One-dimensional array expected')
            ]);
        }
        foreach ($existing as $i => $e) {
            $existing[$i] = (int) $e;
        }
        $response = [
            'status' => 'OK'
        ];

        if (!empty($post['add'])) {
            $newBlogPost = $blog_model->getBlogPostById($post['add'] + 0);
            if (!empty($newBlogPost)) {
                if ($newBlogPost['author'] === $authorId) {
                    $existing[] = (int) ($post['add'] ?? 0);
                    $response['new_item'] = $this->getViewAsText(
                        'ajax/bridge_blog_series_item',
                        [
                            'item' => [
                                'name' => $newBlogPost['title'],
                                'post' => $newBlogPost['postid'],
                                'data-id' => null,
                            ]
                        ]
                    );
                }
            }
        }

        $series = $blog_model->listPostsForAuthor($authorId, $existing);
        $response['options'] = $this->getViewAsText(
            'ajax/bridge_blog_series_select_blogpost',
            [
                'items' => $series
            ]
        );

        \Airship\json_response($response);
    }

    /**
     * @route ajax/get_perms_user
     */
    public function getPermsForUser(): void
    {
        if (!$this->isSuperUser()) {
            \Airship\json_response(
                [
                    'status' => 'ERROR',
                    'message' => \__('You are not an administrator.')
                ]
            );
        }
        $post = $this->ajaxPost($_POST, 'csrf_token');
        if (empty($post['username'])) {
            \Airship\json_response(
                [
                    'status' => 'ERROR',
                    'message' => \__('You must enter a username.')
                ]
            );
        }
        if (empty($post['context'])) {
            \Airship\json_response(
                [
                    'status' => 'ERROR',
                    'message' => \__('No context provided.')
                ]
            );
        }
        if (empty($post['cabin'])) {
            \Airship\json_response(
                [
                    'status' => 'ERROR',
                    'message' => \__('No cabin provided.')
                ]
            );
        }
        $this->getPermissionsDataForUser(
            (int) $post['context'],
            $post['username'],
            $post['cabin']
        );
    }

    /**
     * @route ajax/authors_blog_series
     * @throws \TypeError
     */
    public function getSeriesForAuthor(): void
    {
        $auth_model = $this->model('Author');
        if (!($auth_model instanceof Author)) {
            throw new \TypeError(
                \trk('errors.type.wrong_class', Author::class)
            );
        }
        $blog_model = $this->model('Blog');
        if (!($blog_model instanceof Blog)) {
            throw new \TypeError(
                \trk('errors.type.wrong_class', Blog::class)
            );
        }

        $post = $this->ajaxPost($_POST, 'csrf_token');
        if (empty($post['author'])) {
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => \__('No author selected.')
            ]);
        }
        $authorId = (int) ($post['author'] ?? 0);
        if (!$this->isSuperUser()) {
            $authors = $auth_model->getAuthorIdsForUser(
                $this->getActiveUserId()
            );
            if (!\in_array($authorId, $authors)) {
                \Airship\json_response([
                    'status' => 'ERROR',
                    'message' => \__('You do not have permission to access this author\'s posts.')
                ]);
            }
        }
        $existing = $post['existing'] ?? [];
        if (!\is1DArray($existing)) {
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => \__('One-dimensional array expected')
            ]);
        }
        foreach ($existing as $i => $e) {
            $existing[$i] = (int) $e;
        }
        $response = [
            'status' => 'OK'
        ];

        if (!empty($post['add'])) {
            $add = (int) ($post['add'] ?? 0);
            $newSeries = $blog_model->getSeries($add);
            if (!empty($newSeries)) {
                if ($newSeries['author'] === $authorId) {
                    $existing[] = $add;
                    $response['new_item'] = $this->getViewAsText(
                        'ajax/bridge_blog_series_item',
                        [
                            'item' => [
                                'name' => $newSeries['name'],
                                'series' => $newSeries['seriesid'],
                                'data-id' => null,
                            ]
                        ]
                    );
                }
            }
        }

        $existing = $blog_model->getAllSeriesParents($existing);

        $series = $blog_model->getSeriesForAuthor($authorId, $existing);
        $response['options'] = $this->getViewAsText(
            'ajax/bridge_blog_series_select_series',
            [
                'items' => $series
            ]
        );

        \Airship\json_response($response);
    }

    /**
     * @route ajax/permission_test
     */
    public function permissionTest(): void
    {
        if (!$this->isSuperUser()) {
            \Airship\json_response(
                [
                    'status' => 'ERROR',
                    'message' => \__('You are not an administrator.')
                ]
            );
        }

        $post = $this->ajaxPost($_POST, 'csrf_token');
        if (empty($post['url'])) {
            \Airship\json_response(
                [
                    'status' => 'ERROR',
                    'message' => \__('You must enter a URL.')
                ]
            );
        }
        try {
            $cabin = $this->getCabinNameFromURL($post['url']);
            $this->getPermissionDataForURL($post['url'], $cabin);
        } catch (CabinNotFound $ex) {
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => \__('URL does not resolve to an existing Cabin.')
            ]);
        }
    }

    /**
     * @route ajax/rich_text_preview
     */
    public function richTextPreview(): void
    {
        $post = $this->ajaxPost($_POST, 'csrf_token');
        if (\Airship\all_keys_exist(['format', 'body'], $post)) {
            switch ($post['format']) {
                case 'HTML':
                case 'Rich Text':
                    \Airship\json_response([
                        'status' => 'OK',
                        'body' => \Airship\ViewFunctions\get_purified($post['body'] ?? '')
                    ]);
                    break;
                case 'Markdown':
                    \Airship\json_response([
                        'status' => 'OK',
                        'body' => \Airship\ViewFunctions\render_purified_markdown(
                            $post['body'] ?? '',
                            true
                        )
                    ]);
                    break;
                case 'RST':
                    \Airship\json_response([
                        'status' => 'OK',
                        'body' => \Airship\ViewFunctions\get_purified(
                            \Airship\ViewFunctions\render_rst($post['body'] ?? '', true)
                        )
                    ]);
                    break;
                default:
                    \Airship\json_response([
                        'status' => 'ERROR',
                        'message' => 'Unknown format: ' . $post['format']
                    ]);
            }
        }
        \Airship\json_response([
            'status' => 'ERROR',
            'message' => \__('Incomplete request')
        ]);
    }

    /**
     * @param string $url
     * @param string $cabin
     * @throws \TypeError
     */
    protected function getPermissionDataForURL(string $url, string $cabin): void
    {
        $perm_model = $this->model('Permissions');
        if (!($perm_model instanceof Permissions)) {
            throw new \TypeError(
                \trk('errors.type.wrong_class', Permissions::class)
            );
        }

        $actions = $perm_model->getActionNames($cabin);
        $contexts = $perm_model->getContextsForURI($url, $cabin);
        $contextIds = $perm_model->getContextIds($url, $cabin);
        $tree = $perm_model->buildMultiContextGroupTree(
            $cabin,
            $contextIds,
            $actions
        );
        $list = $perm_model->buildMultiContextUserList(
            $cabin,
            $contextIds,
            $actions
        );

        $this->view(
            'perms/test',
            [
                'cabin' =>
                    $cabin,
                'actions' =>
                    $actions,
                'contexts' =>
                    $contexts,
                'permissions' =>
                    $tree,
                'userlist' =>
                    $list
            ]
        );
    }

    /**
     * @param int $contextId
     * @param string $username
     * @param string $cabin
     * @throws \TypeError
     */
    protected function getPermissionsDataForUser(
        int $contextId,
        string $username,
        string $cabin
    ): void {
        $perm_model = $this->model('Permissions');
        if (!($perm_model instanceof Permissions)) {
            throw new \TypeError(
                \trk('errors.type.wrong_class', Permissions::class)
            );
        }
        $user_model = $this->model('UserAccounts');
        if (!($user_model instanceof UserAccounts)) {
            throw new \TypeError(
                \trk('errors.type.wrong_class', UserAccounts::class)
            );
        }

        $user = $user_model->getUserByUsername($username, true);
        if (empty($user)) {
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => \__('There is no user with that username in the system')
            ]);
        }
        $userPerms = $perm_model->getUserPerms($user['userid'], $contextId);
        $actions = $perm_model->getActionNames($cabin);
        $perms = [];
        foreach ($actions as $action) {
            $perms[$action] = \in_array($action, $userPerms);
        }

        \Airship\json_response([
            'status' => 'OK',
            'message' => $this->viewRender(
                'perms/user',
                [
                    'user' =>  $user,
                    'actions' => $actions,
                    'perms' => $perms
                ]
            ),
        ]);
    }

    /**
     * @route ajax/authors_save_photo
     */
    public function saveAuthorsPhoto(): void
    {
        $auth_model = $this->model('Author');
        if (!($auth_model instanceof Author)) {
            throw new \TypeError(
                \trk('errors.type.wrong_class', Author::class)
            );
        }
        $post = $this->ajaxPost($_POST, 'csrf_token');
        $authorId = (int) $post['author'];
        if (!$this->isSuperUser()) {
            $authors = $auth_model->getAuthorIdsForUser(
                $this->getActiveUserId()
            );
            if (!\in_array($authorId, $authors)) {
                \Airship\json_response([
                    'status' => 'ERROR',
                    'message' => \__('You do not have permission to access this author\'s posts.')
                ]);
            }
            if (!$auth_model->userIsOwner($authorId)) {
                \Airship\json_response([
                    'status' => 'ERROR',
                    'message' => \__('You do not have permission to access this author\'s posts.')
                ]);
            }
        }
        if (!\Airship\all_keys_exist(['cabin', 'context', 'author', 'filename'], $post)) {
            \Airship\json_response([
                'keys' => array_keys($post),
                'status' => 'ERROR',
                'message' => 'Insufficient parameters'
            ]);
        }
        $result = $auth_model->savePhotoChoice(
            $authorId,
            $post['context'],
            $post['cabin'],
            $post['filename']
        );
        if (!$result) {
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => 'Could not save photo choice.',
                'photo' => null
            ]);
        }
        \Airship\json_response([
            'status' => 'OK',
            'message' => 'Saved!',
        ]);
    }
}
