<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Landing;

use \Airship\Engine\State;
use \ParagonIE\Halite\Alerts\InvalidMessage;
use \Psr\Log\LogLevel;
use \ReCaptcha\ReCaptcha;

require_once __DIR__.'/gear.php';

class Account extends LandingGear
{
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
            return \Airship\redirect($this->airship_cabin_prefix);
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
                        return $this->processBoard($p);
                    } else {
                        return $this->lens('board', [
                            'config' => $this->config(),
                            'title' => 'All Aboard!'
                        ]);
                    }
                }
            } else {
                return $this->processBoard($p);
            }
        }
        return $this->lens('board', [
            'config' => $this->config(),
            'title' => 'All Aboard!'
        ]);
    }

    /**
     * AJAX + JSON API to see if a username is taken or invalid.
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
     * @route login
     *
     * Handle login requests
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
            return \Airship\redirect($this->airship_cabin_prefix);
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
            return \Airship\redirect($this->airship_cabin_prefix);
        }
        $prefs = $this->acct->getUserPreferences($this->getActiveUserId());
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
            return \Airship\redirect($this->airship_cabin_prefix);
        }
        $account = $this->acct->getUserAccount($this->getActiveUserId());
        $p = $this->post();
        if (!empty($p)) {
            return $this->processAccountUpdate($p, $account);
        }
        $this->lens('my_account', ['account' => $account]);
    }

    /**
     * @route my
     */
    public function myIndex()
    {
        return \Airship\redirect($this->airship_cabin_prefix . '/my/account');
    }

    /**
     * @route recover-account
     */
    public function recoverAccount()
    {
        if ($this->isLoggedIn())  {
            return \Airship\redirect($this->airship_cabin_prefix);
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
                return $this->lens('my_account', [
                    'post_response' => [
                        'message' => \__('Supplied password is too weak.'),
                        'status' => 'error'
                    ]
                ]);
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
            return $this->lens('my_account', [
                'account' => $post,
                'post_response' => [
                    'message' => \__('Account was saved successfully.'),
                    'status' => 'success'
                ]
            ]);
        }
        return $this->lens('my_account', [
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
            return $this->lens('board', [
                'post_response' => [
                    'message' => \__('Please fill out the form entirely'),
                    'status' => 'error'
                ]
            ]);
        }

        if ($this->acct->isUsernameTaken($post['username'])) {
            return $this->lens('board', [
                'post_response' => [
                    'message' => \__('Username is not available'),
                    'status' => 'error'
                ]
            ]);
        }

        if ($this->acct->isPasswordWeak($post)) {
            return $this->lens('board', [
                'post_response' => [
                    'message' => \__('Supplied password is too weak.'),
                    'status' => 'error'
                ]
            ]);
        }
        
        $userid = $this->acct->createUser($post);
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
            return $this->lens('login', [
                'post_response' => [
                    'message' => \__('Please fill out the form entirely'),
                    'status' => 'error'
                ]
            ]);
        }
        try {
            $userid = $this->airship_auth->login($post['username'], $post['passphrase']);
        } catch (InvalidMessage $e) {
            $this->log(
                'InvalidMessage Exception on Login; probable cause: password column was corrupted',
                LogLevel::CRITICAL,
                [
                    'exception' => \Airship\throwableToArray($e)
                ]
            );
            return $this->lens('login', [
                'post_response' => [
                    'message' => \__('Incorrect username or passphrase. Please try again.'),
                    'status' => 'error'
                ]
            ]);
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
            return $this->lens('login', [
                'post_response' => [
                    'message' => \__('Incorrect username or passphrase. Please try again.'),
                    'status' => 'error'
                ]
            ]);
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
    protected function findMotif(array $motifs, string $supplier, string $motifName): bool
    {
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
