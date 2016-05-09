<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Landing;

use \Airship\Cabin\Bridge\Blueprint\UserAccounts;
use \Airship\Engine\{
    Bolt\Security,
    Security\Authentication,
    State
};
use \ParagonIE\Halite\Alerts\InvalidMessage;
use \ParagonIE\Halite\{
    Cookie,
    Symmetric\EncryptionKey
};
use \Psr\Log\LogLevel;

require_once __DIR__.'/gear.php';

/**
 * Class Account
 *
 * Landing for user account stuff. Also contains the login and registration forms.
 *
 * @package Airship\Cabin\Bridge\Landing
 */
class Account extends LandingGear
{
    use Security;

    protected $acct;

    public function __construct()
    {
        if (IDE_HACKS) {
            $db = \Airship\get_database();
            $this->acct = new UserAccounts($db);
            $this->airship_cookie = new Cookie(
                new EncryptionKey(\random_bytes(32))
            );
            $this->airship_auth = new Authentication(
                new EncryptionKey(\random_bytes(32)),
                $db
            );
            $this->airship_perms = new Permissions($db);
        }
    }

    /**
     * We initialize this after the constructor is done.
     */
    public function airshipLand()
    {
        parent::airshipLand();
        $this->acct = $this->blueprint('UserAccounts');
    }

    /**
     * Process the /board API endpoint.
     *
     * @route board
     */
    public function board()
    {
        if ($this->isLoggedIn())  {
            // You're already logged in!
            \Airship\redirect($this->airship_cabin_prefix);
        }

        $p = $this->post();
        if (!empty($p)) {
            // Optional: CAPTCHA enforcement
            if ($this->config('board.captcha')) {
                if (isset($p['g-recaptcha-response'])) {
                    $rc = \Airship\getReCaptcha(
                        $this->config('recaptcha.secret-key'),
                        $this->config('recaptcha.curl-opts') ?? []
                    );
                    $resp = $rc->verify($p['g-recaptcha-response'], $_SERVER['REMOTE_ADDR']);
                    if ($resp->isSuccess()) {
                        $this->processBoard($p);
                        exit;
                    } else {
                        $this->lens('board', [
                            'config' => $this->config(),
                            'title' => 'All Aboard!'
                        ]);
                        exit;
                    }
                }
            } else {
                $this->processBoard($p);
                exit;
            }
        }
        $this->lens('board', [
            'config' => $this->config(),
            'title' => 'All Aboard!'
        ]);
    }

    /**
     * AJAX + JSON API to see if a username is taken or invalid.
     *
     * @todo move to PublicAjax
     */
    public function checkUsername()
    {
        // If you didn't supply a username, it's not available.
        if (!\array_key_exists('username', $_POST)) {
            \Airship\json_response([
                'status' => 'error',
                'message' => \__('You did not supply a username'),
                'result' => []
            ]);
        }
        
        // Did someone else reserve this username?
        if ($this->acct->isUsernameTaken($_POST['username'])) {
            \Airship\json_response([
                'status' => 'success',
                'message' => \__('Username is not available'),
                'result' => [
                    'available' => false
                ]
            ]);
        }

        if ($this->acct->isUsernameInvalid($_POST['username'])) {
            \Airship\json_response([
                'status' => 'success',
                'message' => \__('Username is not available'),
                'result' => [
                    'available' => false
                ]
            ]);
        }
        
        // The username has not been reserved.
        \Airship\json_response([
            'status' => 'success',
            'message' => \__('Username is available'),
            'result' => [
                'available' => true
            ]
        ]);
    }

    /**
     * Handle login requests
     *
     * @route login
     */
    public function login()
    {
        $p = $this->post();
        if (!empty($p)) {
            return $this->processLogin($p);
        }
        return $this->lens('login');
    }

    /**
     * CSRF-resistant logout script
     *
     * @route logout/(.*)
     * @param string $token
     * @return mixed
     */
    public function logout(string $token)
    {
        if (!$this->isLoggedIn())  {
            \Airship\redirect($this->airship_cabin_prefix);
        }

        $state = State::instance();
        $idx = $state->universal['session_index']['logout_token'];
        if (\array_key_exists($idx, $_SESSION)) {
            if (\hash_equals($token, $_SESSION[$idx])) {
                $this->completeLogOut();
            }
        }
        \Airship\redirect($this->airship_cabin_prefix);
    }

    /**
     * Allows users to select which Motif to use
     *
     * @route my/preferences
     */
    public function preferences()
    {
        if (!$this->isLoggedIn())  {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        $prefs = $this->acct->getUserPreferences(
            $this->getActiveUserId()
        );
        $cabins = [];
        $motifs = [];
        foreach ($this->getCabinNames() as $cabin) {
            $cabins[] = $cabin;
            $filename = ROOT . '/tmp/cache/' . $cabin . '.motifs.json';
            if (\file_exists($filename)) {
                $motifs[$cabin] = \Airship\loadJSON($filename);
            } else {
                $motifs[$cabin] = [];
            }

        }

        $post = $this->post();
        if (!empty($post)) {
            if ($this->savePreferences($post['prefs'], $cabins, $motifs)) {
                $prefs = $post['prefs'];
            }
        }

        return $this->lens('preferences', [
            'prefs' =>
                $prefs,
            'motifs' =>
                $motifs
        ]);
    }

    /**
     * @route my/account
     */
    public function my()
    {
        if (!$this->isLoggedIn())  {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        $account = $this->acct->getUserAccount($this->getActiveUserId());
        $p = $this->post();
        if (!empty($p)) {
            $this->processAccountUpdate($p, $account);
            exit;
        }
        $this->lens('my_account', ['account' => $account]);
    }

    /**
     * @route my
     */
    public function myIndex()
    {
        \Airship\redirect($this->airship_cabin_prefix . '/my/account');
    }

    /**
     * @route recover-account
     */
    public function recoverAccount()
    {
        if ($this->isLoggedIn())  {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        $this->lens('recover_account');
    }

    /**
     * Process a user account update
     *
     * @param array $post
     * @param array $account
     */
    protected function processAccountUpdate(array $post = [], array $account = [])
    {
        $state = State::instance();
        $idx = $state->universal['session_index']['user_id'];

        if (!empty($post['passphrase'])) {
            // Lazy hack
            $post['username'] = $account['username'];
            if ($this->acct->isPasswordWeak($post)) {
                $this->lens('my_account', [
                    'post_response' => [
                        'message' => \__('Supplied password is too weak.'),
                        'status' => 'error'
                    ]
                ]);
                exit;
            }

            // Log password changes as a WARNING
            $this->log(
                'Changing password for user, ' . $account['username'],
                LogLevel::WARNING
            );
            $this->acct->setPassphrase($post['passphrase'], $_SESSION[$idx]);
            unset($post['username'], $post['passphrase']);
        }

        if ($this->acct->updateAccountInfo($post, $account)) {
            $this->lens('my_account', [
                'account' => $post,
                'post_response' => [
                    'message' => \__('Account was saved successfully.'),
                    'status' => 'success'
                ]
            ]);
            exit;
        }
        $this->lens('my_account', [
            'account' => $post,
            'post_response' => [
                'message' => \__('Account was not saved successfully.'),
                'status' => 'error'
            ]
        ]);
    }
    
    /**
     * Process a user account registration request
     *
     * @param array $post
     */
    protected function processBoard(array $post = [])
    {
        if (!\Airship\all_keys_exist(['username', 'passphrase'], $post)) {
            $this->lens('board', [
                'post_response' => [
                    'message' => \__('Please fill out the form entirely'),
                    'status' => 'error'
                ]
            ]);
            exit;
        }

        if ($this->acct->isUsernameTaken($post['username'])) {
            $this->lens('board', [
                'post_response' => [
                    'message' => \__('Username is not available'),
                    'status' => 'error'
                ]
            ]);
            exit;
        }

        if ($this->acct->isPasswordWeak($post)) {
            $this->lens('board', [
                'post_response' => [
                    'message' => \__('Supplied password is too weak.'),
                    'status' => 'error'
                ]
            ]);
            exit;
        }

        $this->acct->createUser($post);
        \Airship\redirect($this->airship_cabin_prefix);
    }
    
    /**
     * Handle user authentication
     *
     * @param array $post
     */
    protected function processLogin(array $post = [])
    {
        $state = State::instance();

        if (!\Airship\all_keys_exist(['username', 'passphrase'], $post)) {
            $this->lens('login', [
                'post_response' => [
                    'message' => \__('Please fill out the form entirely'),
                    'status' => 'error'
                ]
            ]);
            exit;
        }
        try {
            $userid = $this->airship_auth->login(
                $post['username'],
                new HiddenString($post['passphrase'])
            );
        } catch (InvalidMessage $e) {
            $this->log(
                'InvalidMessage Exception on Login; probable cause: password column was corrupted',
                LogLevel::CRITICAL,
                [
                    'exception' => \Airship\throwableToArray($e)
                ]
            );
            $this->lens('login', [
                'post_response' => [
                    'message' => \__('Incorrect username or passphrase. Please try again.'),
                    'status' => 'error'
                ]
            ]);
            exit;
        }

        if (!empty($userid)) {
            $idx = $state->universal['session_index']['user_id'];
            $_SESSION[$idx] = $userid;

            if (!empty($post['remember'])) {
                $this->airship_cookie->store(
                    $state->universal['cookie_index']['auth_token'],
                    $this->airship_auth->createAuthToken($userid),
                    \time() + ($state->universal['long-term-auth-expire'] ?? self::DEFAULT_LONGTERMAUTH_EXPIRE),
                    '/',
                    '',
                    false,
                    true
                );
            }
            \Airship\redirect($this->airship_cabin_prefix);
        } else {
            $this->lens('login', [
                'post_response' => [
                    'message' => \__('Incorrect username or passphrase. Please try again.'),
                    'status' => 'error'
                ]
            ]);
            exit;
        }
    }

    /**
     * Save a user's preferences
     *
     * @param array $prefs
     * @param array $cabins
     * @param array $motifs
     * @return bool
     */
    protected function savePreferences(
        array $prefs = [],
        array $cabins = [],
        array $motifs = []
    ): bool {
        // Validate the motifs
        foreach ($prefs['motif'] as $cabin => $selectedMotif) {
            if (!\in_array($cabin, $cabins)) {
                unset($prefs['motif'][$cabin]);
                continue;
            }
            if (empty($selectedMotif)) {
                $prefs['motif'][$cabin] = null;
                continue;
            }
            list ($supplier, $motifName) = \explode('/', $selectedMotif);
            if (!$this->findMotif($motifs[$cabin], $supplier, $motifName)) {
                $prefs['motif'][$cabin] = null;
                continue;
            }
        }

        if ($this->acct->updatePreferences($this->getActiveUserId(), $prefs)) {
            $this->storeLensVar('post_response', [
                'message' => \__('Preferences saved successfully.'),
                'status' => 'success'
            ]);
            return true;
        }
        return false;
    }

    /**
     * Is this motif part of this cabin?
     *
     * @param array $motifs
     * @param string $supplier
     * @param string $motifName
     * @return bool
     */
    protected function findMotif(
        array $motifs,
        string $supplier,
        string $motifName
    ): bool {
        foreach ($motifs as $id => $data) {
            if (
                $data['config']['supplier'] === $supplier
                    &&
                $data['config']['name'] === $motifName
            ) {
                return true;
            }
        }
        return false;
    }
}
