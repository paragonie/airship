<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Landing;

use \Airship\Alerts\Security\UserNotFound;
use \Airship\Cabin\Bridge\Blueprint\UserAccounts;
use \Airship\Engine\{
    AutoPilot,
    Bolt\Security,
    Gears,
    Security\HiddenString,
    Security\Util,
    State
};
use \ParagonIE\GPGMailer\GPGMailer;
use \ParagonIE\Halite\{
    Alerts\InvalidMessage,
    Util as CryptoUtil
};
use \ParagonIE\MultiFactor\OTP\TOTP;
use \ParagonIE\MultiFactor\Vendor\GoogleAuth;
use \Psr\Log\LogLevel;
use \Zend\Mail\{
    Message,
    Transport\Sendmail
};

require_once __DIR__.'/init_gear.php';

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

    /**
     * @var UserAccounts
     */
    protected $acct;

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
        if (!$this->config('board.enabled')) {
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
     * Handle login requests
     *
     * @route login
     */
    public function login()
    {
        if ($this->isLoggedIn())  {
            // You're already logged in!
            \Airship\redirect($this->airship_cabin_prefix);
        }
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
     * @route my/account
     */
    public function my()
    {
        if (!$this->isLoggedIn())  {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        $account = $this->acct->getUserAccount($this->getActiveUserId());
        $gpg_public_key = '';
        if (!empty($account['gpg_public_key'])) {
            $gpg_public_key = $this->getGPGPublicKey($account['gpg_public_key']);
        }
        $p = $this->post();
        if (!empty($p)) {
            $this->processAccountUpdate($p, $account, $gpg_public_key);
            exit;
        }
        $this->lens(
            'my_account',
            [
                'account' => $account,
                'gpg_public_key' => $gpg_public_key
            ]
        );
    }

    /**
     * @route my
     */
    public function myIndex()
    {
        \Airship\redirect($this->airship_cabin_prefix . '/my/account');
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
        foreach ($this->getCabinNamespaces() as $cabin) {
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
     * @route recover-account
     */
    public function recoverAccount(string $token = '')
    {
        if ($this->isLoggedIn())  {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        $enabled = $this->config('password-reset.enabled');
        if (empty($enabled)) {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        $post = $this->post();
        if ($post) {
            if ($this->processRecoverAccount($post)) {
                \Airship\redirect($this->airship_cabin_prefix . '/login');
            } else {
                $this->storeLensVar(
                    'form_message',
                    \__("User doesn't exist or opted out of account recovery.")
                );
            }
        }
        if (!empty($token)) {
            $this->processRecoveryToken($token);
        }
        $this->lens('recover_account');
    }

    /**
     * Returns the user's QR code.
     *
     */
    public function twoFactorSetupQRCode()
    {
        if (!$this->isLoggedIn())  {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        $gauth = $this->twoFactorPreamble();
        $user = $this->acct->getUserAccount($this->getActiveUserId());

        \header('Content-Type: image/png');
        $gauth->makeQRCode(
            null,
            'php://output',
            $user['username'] . '@' . $_SERVER['HTTP_HOST'],
            $this->config('two-factor.issuer') ?? '',
            $this->config('two-factor.label') ?? ''
        );
    }

    /**
     *
     */
    public function twoFactorSetup()
    {
        if (!$this->isLoggedIn())  {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        $this->twoFactorPreamble();
        $userID = $this->getActiveUserId();
        $post = $this->post();
        if ($post) {
            $this->acct->toggleTwoFactor($userID, $post);
        }
        $user = $this->acct->getUserAccount($userID);
        
        $this->lens(
            'two_factor',
            [
                'enabled' => $user['enable_2factor'] ?? false
            ]
        );
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

    /**
     * Return the public key corresponding to a fingerprint
     *
     * @param string $fingerprint
     * @return string
     */
    protected function getGPGPublicKey(string $fingerprint): string
    {
        $state = State::instance();
        try {
            return \trim(
                $state->gpgMailer->export($fingerprint)
            );
        } catch (\Crypt_GPG_Exception $ex) {
            return '';
        }
    }

    /**
     * Process a user account update
     *
     * @param array $post
     * @param array $account
     * @param string $gpg_public_key
     */
    protected function processAccountUpdate(
        array $post = [],
        array $account = [],
        string $gpg_public_key = ''
    ) {
        $state = State::instance();
        $idx = $state->universal['session_index']['user_id'];

        if (!empty($post['passphrase'])) {
            // Lazy hack
            $post['username'] = $account['username'];
            if ($this->acct->isPasswordWeak($post)) {
                $this->lens(
                    'my_account',
                    [
                        'account' => $account,
                        'gpg_public_key' => $gpg_public_key,
                        'post_response' => [
                            'message' => \__('Supplied password is too weak.'),
                            'status' => 'error'
                        ]
                    ]
                );
                exit;
            }

            // Log password changes as a WARNING
            $this->log(
                'Changing password for user, ' . $account['username'],
                LogLevel::WARNING
            );
            $this->acct->setPassphrase(new HiddenString($post['passphrase']), $_SESSION[$idx]);
            if ($this->config('password-reset.logout')) {
                $this->acct->invalidateLongTermAuthTokens($_SESSION[$idx]);

                // We're not logging ourselves out!
                $_SESSION['session_canary'] = $this->acct->createSessionCanary($_SESSION[$idx]);
            }
            unset($post['username'], $post['passphrase']);
        }

        if ($this->acct->updateAccountInfo($post, $account)) {
            // Refresh:
            $account = $this->acct->getUserAccount($this->getActiveUserId());
            $gpg_public_key = $this->getGPGPublicKey($account['gpg_public_key']);
            $this->lens(
                'my_account',
                [
                    'account' => $account,
                    'gpg_public_key' => $gpg_public_key,
                    'post_response' => [
                        'message' => \__('Account was saved successfully.'),
                        'status' => 'success'
                    ]
                ]
            );
            exit;
        }
        $this->lens(
            'my_account',
            [
                'account' => $post,
                'gpg_public_key' => $gpg_public_key,
                'post_response' => [
                    'message' => \__('Account was not saved successfully.'),
                    'status' => 'error'
                ]
            ]
        );
    }
    
    /**
     * Process a user account registration request
     *
     * @param array $post
     */
    protected function processBoard(array $post = [])
    {
        $state = State::instance();

        if (!\Airship\all_keys_exist(['username', 'passphrase'], $post)) {
            $this->lens(
                'board',
                [
                    'post_response' => [
                        'message' => \__('Please fill out the form entirely'),
                        'status' => 'error'
                    ]
                ]
            );
            exit;
        }

        if ($this->acct->isUsernameTaken($post['username'])) {
            $this->lens(
                'board',
                [
                    'post_response' => [
                        'message' => \__('Username is not available'),
                        'status' => 'error'
                    ]
                ]
            );
            exit;
        }

        if ($this->acct->isPasswordWeak($post)) {
            $this->lens(
                'board',
                [
                    'post_response' => [
                        'message' => \__('Supplied password is too weak.'),
                        'status' => 'error'
                    ]
                ]
            );
            exit;
        }

        $userID = $this->acct->createUser($post);
        $idx = $state->universal['session_index']['user_id'];
        $_SESSION[$idx] = (int) $userID;

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
            $userID = $this->airship_auth->login(
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

        if (!empty($userID)) {
            $userID = (int) $userID;
            $user = $this->acct->getUserAccount($userID);
            if ($user['enable_2factor']) {
                if (empty($post['two_factor'])) {
                    $post['two_factor'] = '';
                }
                $gauth = $this->twoFactorPreamble($userID);
                $checked = $gauth->validateCode($post['two_factor'], \time());
                if (!$checked) {
                    $this->lens(
                        'login',
                        [
                            'post_response' => [
                                'message' => \__('Incorrect username or passphrase. Please try again.'),
                                'status' => 'error'
                            ]
                        ]
                    );
                    exit;
                }
            }
            if ($user['session_canary']) {
                $_SESSION['session_canary'] = $user['session_canary'];
            } elseif ($this->config('password-reset.logout')) {
                $_SESSION['session_canary'] = $this->acct->createSessionCanary($userID);
            }

            $idx = $state->universal['session_index']['user_id'];

            // Regenerate session ID:
            \session_regenerate_id(true);

            $_SESSION[$idx] = (int) $userID;

            if (!empty($post['remember'])) {
                $autoPilot = Gears::getName('AutoPilot');
                if (IDE_HACKS) {
                    $autoPilot = new AutoPilot();
                }
                $httpsOnly = (bool) $autoPilot::isHTTPSConnection();
                
                $this->airship_cookie->store(
                    $state->universal['cookie_index']['auth_token'],
                    $this->airship_auth->createAuthToken($userID),
                    \time() + ($state->universal['long-term-auth-expire'] ?? self::DEFAULT_LONGTERMAUTH_EXPIRE),
                    '/',
                    $state->universal['session_config']['cookie_domain'] ?? '',
                    $httpsOnly ?? false,
                    true
                );
            }
            \Airship\redirect($this->airship_cabin_prefix);
        } else {
            $this->lens(
                'login',
                [
                    'post_response' => [
                        'message' => \__('Incorrect username or passphrase. Please try again.'),
                        'status' => 'error'
                    ]
                ]
            );
            exit;
        }
    }

    /**
     * Process account recovery
     *
     * @param array $post
     * @return bool
     */
    protected function processRecoverAccount(array $post): bool
    {
        try {
            $recoverInfo = $this->acct->getRecoveryInfo($post['forgot_passphrase_for']);
        } catch (UserNotFound $ex) {
            // Username not found. Is this a harvester?
            $this->log(
                'Password reset attempt for nonexistent user.',
                LogLevel::NOTICE,
                [
                    'username' => $post['forgot_passphrase_for']
                ]
            );
            return false;
        }
        if (!$recoverInfo['allow_reset'] || empty($recoverInfo['email'])) {
            // Opted out or no email address? Act like the user doesn't exist.
            return false;
        }
        $token = $this->acct->createRecoveryToken((int) $recoverInfo['userid']);
        if (empty($token)) {
            return false;
        }

        $state = State::instance();
        if (IDE_HACKS) {
            $state->mailer = new Sendmail();
            $state->gpgMailer = new GPGMailer($state->mailer);
        }

        $message = (new Message())
            ->addTo($recoverInfo['email'], $post['username'])
            ->setSubject('Password Reset')
            ->setFrom($state->universal['email']['from'] ?? 'no-reply@' . $_SERVER['HTTP_HOST'])
            ->setBody($this->recoveryMessage($token));

        try {
            if (!empty($recoverInfo['gpg_public_key'])) {
                // This will be encrypted with the user's public key:
                $state->gpgMailer->send($message, $recoverInfo['gpg_public_key']);
            } else {
                // This will be sent as-is:
                $state->mailer->send($message);
            }
        } catch (\Zend\Mail\Exception\InvalidArgumentException $ex) {
            return false;
        }
        return true;
    }

    /**
     * If the token is valid, log in as the user.
     *
     * @param string $token
     */
    protected function processRecoveryToken(string $token)
    {
        if (Util::stringLength($token) < UserAccounts::RECOVERY_CHAR_LENGTH) {
            \Airship\redirect($this->airship_cabin_prefix . '/login');
        }
        $selector = Util::subString($token, 0, 32);
        $validator = Util::subString($token, 32);

        $ttl = (int) $this->config('password-reset.ttl');
        if (empty($ttl)) {
            \Airship\redirect($this->airship_cabin_prefix . '/login');
        }
        $recoveryInfo = $this->acct->getRecoveryData($selector, $ttl);
        if (empty($recoveryInfo)) {
            \Airship\redirect($this->airship_cabin_prefix . '/login');
        }
        $calc = CryptoUtil::keyed_hash(
            $validator,
            CryptoUtil::raw_hash('' . $recoveryInfo['userid'])
        );
        if (\hash_equals($recoveryInfo['hashedtoken'], $calc)) {
            $state = State::instance();
            $idx = $state->universal['session_index']['user_id'];
            $_SESSION[$idx] = (int) $recoveryInfo['userid'];
            \Airship\redirect($this->airship_cabin_prefix . '/my/account');
        }
        \Airship\redirect($this->airship_cabin_prefix . '/login');
    }

    /**
     * @param string $token
     * @return string
     */
    protected function recoveryMessage(string $token): string
    {
        return \__("To recover your account, visit the URL below.") . "\n\n" .
            \Airship\LensFunctions\cabin_url() . 'forgot-password/' . $token . "\n\n" .
            \__("This access token will expire in an hour.");
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
     * Make sure the secret exists, then get the GoogleAuth object
     *
     * @param int $userID
     * @return GoogleAuth
     * @throws \Airship\Alerts\Security\UserNotLoggedIn
     */
    protected function twoFactorPreamble(int $userID = 0): GoogleAuth
    {
        if (!$userID) {
            $userID = $this->getActiveUserId();
        }
        $secret = $this->acct->getTwoFactorSecret($userID);
        if (empty($secret)) {
            if (!$this->acct->resetTwoFactorSecret($userID)) {
                \Airship\json_response(['test2']);
                \Airship\redirect($this->airship_cabin_prefix);
            }
            $secret = $this->acct->getTwoFactorSecret($userID);
        }
        return new GoogleAuth(
            $secret,
            new TOTP(
                0,
                $this->config('two-factor.period') ?? 30,
                $this->config('two-factor.length') ?? 6
            )
        );
    }
}
