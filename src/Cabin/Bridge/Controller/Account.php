<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Controller;

use Airship\Alerts\Security\UserNotFound;
use Airship\Cabin\Bridge\Model\UserAccounts;
use Airship\Cabin\Bridge\Filter\Account\{
    BoardFilter,
    LoginFilter,
    MyAccountFilter,
    PreferencesFilter,
    RecoveryFilter,
    TwoFactorFilter
};
use Airship\Engine\{
    AutoPilot,
    Bolt\Security,
    Gears,
    State
};
use Airship\Engine\Security\{
    AirBrake,
    Util
};
use ParagonIE\Cookie\{
    Cookie,
    Session
};
use ParagonIE\GPGMailer\GPGMailer;
use ParagonIE\Halite\{
    Alerts\InvalidMessage,
    Asymmetric\Crypto as Asymmetric,
    HiddenString,
    Symmetric\Crypto as Symmetric
};
use ParagonIE\MultiFactor\OTP\TOTP;
use ParagonIE\MultiFactor\Vendor\GoogleAuth;
use Psr\Log\LogLevel;
use Zend\Mail\{
    Exception\InvalidArgumentException,
    Exception\RuntimeException,
    Message,
    Transport\TransportInterface
};
use BaconQrCode\{
    Renderer\Image\Svg,
    Writer as QRCodeWriter
};

require_once __DIR__.'/init_gear.php';

/**
 * Class Account
 *
 * Controller for user account stuff. Also contains the login and registration forms.
 *
 * @package Airship\Cabin\Bridge\Controller
 */
class Account extends ControllerGear
{
    use Security;

    /**
     * @var UserAccounts
     */
    protected $acct;

    /**
     * This function is called after the dependencies have been injected by
     * AutoPilot. Think of it as a user-land constructor.
     */
    public function airshipLand()
    {
        parent::airshipLand();
        $this->storeViewVar('showmenu', true);
        $this->acct = $this->model('UserAccounts');
        $this->includeAjaxToken();
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

        $this->storeViewVar('showmenu', false);
        $post = $this->post(new BoardFilter());
        if (!empty($post)) {
            // Optional: CAPTCHA enforcement
            if ($this->config('board.captcha')) {
                if (isset($post['g-recaptcha-response'])) {
                    $rc = \Airship\getReCaptcha(
                        $this->config('recaptcha.secret-key'),
                        $this->config('recaptcha.curl-opts') ?? []
                    );
                    $resp = $rc->verify(
                        $post['g-recaptcha-response'],
                        $_SERVER['REMOTE_ADDR']
                    );
                    if ($resp->isSuccess()) {
                        $this->processBoard($post);
                        return;
                    }
                    $this->storeViewVar(
                        'post_response',
                        [
                            'status' => 'ERROR',
                            'message' => 'Invalid CAPTCHA response'
                        ]
                    );
                }
            } else {
                $this->processBoard($post);
                return;
            }
        }
        $this->view('board', [
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
        $this->storeViewVar('showmenu', false);
        $post = $this->post(new LoginFilter());
        if (!empty($post)) {
            $this->processLogin($post);
            return;
        }
        $this->view('login');
    }

    /**
     * CSRF-resistant logout script
     *
     * @route logout/(.*)
     * @param string $token
     */
    public function logout(string $token)
    {
        if (!$this->isLoggedIn())  {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        if (\array_key_exists('logout_token', $_SESSION)) {
            if (\hash_equals($token, $_SESSION['logout_token'])) {
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
        $post = $this->post(new MyAccountFilter());
        if (!empty($post)) {
            $this->processAccountUpdate($post, $account, $gpg_public_key);
            return;
        }
        $this->view(
            'my_account',
            [
                'active_link' => 'bridge-link-my-account',
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

        $post = $this->post(PreferencesFilter::fromConfig($cabins, $motifs));
        if (!empty($post)) {
            if ($this->savePreferences($post['prefs'], $cabins, $motifs)) {
                \Airship\redirect(
                    $this->airship_cabin_prefix . '/my/preferences'
                );
            }
        }

        $this->view('preferences', [
            'prefs' =>
                $prefs,
            'motifs' =>
                $motifs
        ]);
    }

    /**
     * A directory of public users
     *
     * @param string $page
     * @route users{_page}
     */
    public function publicDirectory(string $page = '')
    {
        if (!$this->isLoggedIn())  {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        list($offset, $limit) = $this->getOffsetAndLimit($page);
        $directory = $this->acct->getDirectory($offset, $limit);
        $this->view(
            'user_directory',
            [
                'directory' => $directory,
                'pagination' => [
                    'base' => $this->airship_cabin_prefix . '/users',
                    'suffix' => '/',
                    'count' => $this->acct->countPublicUsers(),
                    'page' => (int) \ceil($offset / ($limit ?? 1)) + 1,
                    'per_page' => $limit
                ]
            ]
        );
    }

    /**
     * @route recover-account
     * @param string $token
     */
    public function recoverAccount(string $token = '')
    {
        if ($this->isLoggedIn())  {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        $this->storeViewVar('showmenu', false);
        $enabled = $this->config('password-reset.enabled');
        if (empty($enabled)) {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        $post = $this->post(new RecoveryFilter());
        if ($post) {
            $this->processRecoverAccount($post);
            $this->storeViewVar(
                'form_message',
                \__(
                    "Please check the email associated with this username. " .
                    "If this username is valid (and opted into account " .
                    "recovery), then you should receive an email. If we " .
                    "have your GnuPG public key on file, the email will be " .
                    "encrypted."
                )
            );
        }
        if (!empty($token)) {
            $this->processRecoveryToken($token);
        }
        $this->view('recover_account');
    }

    /**
     * Returns the user's QR code.
     * @route my/account/2-factor/qr-code
     *
     */
    public function twoFactorSetupQRCode()
    {
        if (!$this->isLoggedIn())  {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        $gauth = $this->twoFactorPreamble();
        $user = $this->acct->getUserAccount($this->getActiveUserId());

        if (\extension_loaded('gd')) {
            $this->includeStandardHeaders('image/png');
            $writer = null;
        } else {
            $renderer = new Svg();
            $renderer->setHeight(384);
            $renderer->setWidth(384);
            $writer = new QRCodeWriter($renderer);
            $this->includeStandardHeaders('image/svg+xml');
        }
        $gauth->makeQRCode(
            $writer,
            'php://output',
            $user['username'] . '@' . $_SERVER['HTTP_HOST'],
            $this->config('two-factor.issuer') ?? '',
            $this->config('two-factor.label') ?? ''
        );
    }

    /**
     * @route my/account/2-factor
     */
    public function twoFactorSetup()
    {
        if (!$this->isLoggedIn())  {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        $this->twoFactorPreamble();
        $userID = $this->getActiveUserId();
        $post = $this->post(new TwoFactorFilter());
        if ($post) {
            if ($this->acct->toggleTwoFactor($userID, $post)) {
                \Airship\redirect(
                    $this->airship_cabin_prefix . '/my/account/2-factor'
                );
            }
        }
        $user = $this->acct->getUserAccount($userID);
        
        $this->view(
            'two_factor',
            [
                'active_link' => 'bridge-link-two-factor',
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
        if (!empty($post['passphrase'])) {
            // Lazy hack
            $post['username'] = $account['username'];
            if ($this->acct->isPasswordWeak($post)) {
                $this->view(
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
            }

            // Log password changes as a WARNING
            $this->log(
                'Changing password for user, ' . $account['username'],
                LogLevel::WARNING
            );
            $this->acct->setPassphrase(
                new HiddenString($post['passphrase']),
                $_SESSION['userid']
            );
            if ($this->config('password-reset.logout')) {
                $this->acct->invalidateLongTermAuthTokens($_SESSION['userid']);

                // We're not logging ourselves out!
                $_SESSION['session_canary'] = $this->acct->createSessionCanary(
                    $_SESSION['userid']
                );
            }
            unset($post['username'], $post['passphrase']);
        }

        if (!empty($post['email'])) {
            if (!Util::isValidEmail($post['email'])) {
                $this->view(
                    'my_account',
                    [
                        'account' => $account,
                        'gpg_public_key' => $gpg_public_key,
                        'post_response' => [
                            'message' => \__('The email address you provided is invalid.'),
                            'status' => 'error'
                        ]
                    ]
                );
            }
        }

        if ($this->acct->updateAccountInfo($post, $account)) {
            // Refresh:
            $account = $this->acct->getUserAccount($this->getActiveUserId());
            $gpg_public_key = $this->getGPGPublicKey($account['gpg_public_key']);
            $this->view(
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
        }
        $this->view(
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
     *
     * @throws \Airship\Alerts\Database\QueryError
     * @throws \Airship\Alerts\Router\ControllerComplete
     * @throws \TypeError
     */
    protected function processBoard(array $post = [])
    {
        if (empty($post['username']) || empty($post['passphrase'])) {
            $this->view(
                'board',
                [
                    'post_response' => [
                        'message' => \__('Please fill out the form entirely'),
                        'status' => 'error'
                    ]
                ]
            );
        }

        if ($this->acct->isUsernameTaken($post['username'])) {
            $this->view(
                'board',
                [
                    'post_response' => [
                        'message' => \__('Username is not available'),
                        'status' => 'error'
                    ]
                ]
            );
        }

        if (!empty($post['email'])) {
            if (!Util::isValidEmail($post['email'])) {
                $this->view(
                    'board',
                    [
                        'post_response' => [
                            'message' => \__('The email address you provided is invalid.'),
                            'status' => 'error'
                        ]
                    ]
                );
            }
        }

        if ($this->acct->isPasswordWeak($post)) {
            $this->view(
                'board',
                [
                    'post_response' => [
                        'message' => \__('Supplied password is too weak.'),
                        'status' => 'error'
                    ]
                ]
            );
        }

        $userID = $this->acct->createUser($post);
        $_SESSION['userid'] = (int) $userID;

        \Airship\redirect($this->airship_cabin_prefix);
    }

    /**
     * Handle user authentication
     *
     * @param array $post
     * @throws InvalidMessage
     * @throws \Airship\Alerts\Router\ControllerComplete
     * @throws \Error
     * @throws \ParagonIE\Halite\Alerts\CannotPerformOperation
     * @throws \ParagonIE\Halite\Alerts\InvalidDigestLength
     * @throws \ParagonIE\Halite\Alerts\InvalidType
     * @throws \TypeError
     */
    protected function processLogin(array $post = [])
    {
        $state = State::instance();

        if (empty($post['username']) || empty($post['passphrase'])) {
            $this->view('login', [
                'post_response' => [
                    'message' => \__('Please fill out the form entirely'),
                    'status' => 'error'
                ]
            ]);
        }

        $airBrake = Gears::get('AirBrake');
        if (!($airBrake instanceof AirBrake)) {
            throw new \TypeError(
                \trk('errors.type.wrong_class', AirBrake::class)
            );
        }
        if ($airBrake->failFast($post['username'], $_SERVER['REMOTE_ADDR'])) {
            $this->view('login', [
                'post_response' => [
                    'message' => \__('You are doing that too fast. Please wait a few seconds and try again.'),
                    'status' => 'error'
                ]
            ]);
        } elseif (!$airBrake->getFastExit()) {
            $delay = $airBrake->getDelay($post['username'], $_SERVER['REMOTE_ADDR']);
            if ($delay > 0) {
                \usleep($delay * 1000);
            }
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
            $this->view('login', [
                'post_response' => [
                    'message' => \__('Incorrect username or passphrase. Please try again.'),
                    'status' => 'error'
                ]
            ]);
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
                    $fails = $airBrake->getFailedLoginAttempts(
                        $post['username'],
                        $_SERVER['REMOTE_ADDR']
                    ) + 1;

                    // Instead of the password, seal a timestamped and
                    // signed message saying the password was correct.
                    // We use a signature with a key local to this Airship
                    // so attackers can't just spam a string constant to
                    // make the person decrypting these strings freak out
                    // and assume the password was compromised.
                    //
                    // False positives are bad. This gives the sysadmin a
                    // surefire way to reliably verify that a log entry is
                    // due to two-factor authentication failing.
                    $message = '**Note: The password was correct; ' .
                        ' invalid 2FA token was provided.** ' .
                        (new \DateTime('now'))->format(\AIRSHIP_DATE_FORMAT);
                    $signed = Asymmetric::sign(
                        $message,
                        $state->keyring['notary.online_signing_key']
                    );
                    $airBrake->registerLoginFailure(
                        $post['username'],
                        $_SERVER['REMOTE_ADDR'],
                        $fails,
                        new HiddenString($signed . $message)
                    );
                    $this->view(
                        'login',
                        [
                            'post_response' => [
                                'message' => \__('Incorrect username or passphrase. Please try again.'),
                                'status' => 'error'
                            ]
                        ]
                    );
                }
            }
            if ($user['session_canary']) {
                $_SESSION['session_canary'] = $user['session_canary'];
            } elseif ($this->config('password-reset.logout')) {
                $_SESSION['session_canary'] = $this->acct->createSessionCanary($userID);
            }

            // Regenerate session ID:
            Session::regenerate(true);

            $_SESSION['userid'] = (int) $userID;

            if (!empty($post['remember'])) {
                /** @var AutoPilot $autoPilot */
                $autoPilot = $state->autoPilot;
                if(!\in_array(AutoPilot::class, \Airship\get_ancestors(\get_class($autoPilot)))) {
                    throw new \TypeError(
                        \trk('errors.type.wrong_class', AutoPilot::class)
                    );
                }
                $httpsOnly = (bool) $autoPilot::isHTTPSConnection();
                
                Cookie::setcookie(
                    'airship_token',
                    Symmetric::encrypt(
                        $this->airship_auth->createAuthToken($userID),
                        $state->keyring['cookie.encrypt_key']
                    ),
                    \time() + (
                        $state->universal['long-term-auth-expire']
                            ??
                        2592000
                    ),
                    '/',
                    $state->universal['session_config']['cookie_domain'] ?? '',
                    $httpsOnly ?? false,
                    true
                );
            }
            \Airship\redirect($this->airship_cabin_prefix);
        } else {
            $fails = $airBrake->getFailedLoginAttempts(
                $post['username'],
                $_SERVER['REMOTE_ADDR']
            ) + 1;

            // If the server is setup (with an EncryptionPublicKey) and the
            // number of failures is above the log threshold, this will
            // encrypt the password guess with the public key so that only
            // the person in possession of the secret key can decrypt it.
            $airBrake->registerLoginFailure(
                $post['username'],
                $_SERVER['REMOTE_ADDR'],
                $fails,
                new HiddenString($post['passphrase'])
            );
            $this->view(
                'login',
                [
                    'post_response' => [
                        'message' => \__('Incorrect username or passphrase. Please try again.'),
                        'status' => 'error'
                    ]
                ]
            );
        }
    }

    /**
     * Process account recovery
     *
     * @param array $post
     * @return bool
     * @throws \TypeError
     */
    protected function processRecoverAccount(array $post): bool
    {
        $username = $post['forgot_passphrase_for'];
        $airBrake = Gears::get('AirBrake');
        if (!($airBrake instanceof AirBrake)) {
            throw new \TypeError(
                \trk('errors.type.wrong_class', AirBrake::class)
            );
        }
        $failFast = $airBrake->failFast(
            $username,
            $_SERVER['REMOTE_ADDR'],
            $airBrake::ACTION_RECOVER
        );
        if ($failFast) {
            $this->view(
                'recover_account',
                [
                    'form_message' =>
                        \__('You are doing that too fast. Please wait a few seconds and try again.')
                ]
            );
        } elseif (!$airBrake->getFastExit()) {
            $delay = $airBrake->getDelay(
                $username,
                $_SERVER['REMOTE_ADDR'],
                $airBrake::ACTION_RECOVER
            );
            if ($delay > 0) {
                \usleep($delay * 1000);
            }
        }

        try {
            $recoverInfo = $this->acct->getRecoveryInfo($username);
        } catch (UserNotFound $ex) {
            // Username not found. Is this a harvester?
            $airBrake->registerAccountRecoveryAttempt(
                $username,
                $_SERVER['REMOTE_ADDR']
            );
            $this->log(
                'Password reset attempt for nonexistent user.',
                LogLevel::NOTICE,
                [
                    'username' => $username
                ]
            );
            return false;
        }
        if (!$recoverInfo['allow_reset'] || empty($recoverInfo['email'])) {
            // Opted out or no email address? Act like the user doesn't exist.
            $airBrake->registerAccountRecoveryAttempt(
                $username,
                $_SERVER['REMOTE_ADDR']
            );
            return false;
        }

        $token = $this->acct->createRecoveryToken((int) $recoverInfo['userid']);
        if (empty($token)) {
            return false;
        }

        $state = State::instance();
        if (!($state->mailer instanceof TransportInterface)) {
            throw new \TypeError(
                \trk('errors.type.wrong_class', TransportInterface::class)
            );
        }
        if (!($state->gpgMailer instanceof GPGMailer)) {
            throw new \TypeError(
                \trk('errors.type.wrong_class', GPGMailer::class)
            );
        }

        // Get a valid email address.
        $from = $state->universal['email']['from']
            ??
        'no-reply@' . AutoPilot::getHttpHost();
        if (!Util::isValidEmail($from)) {
            $from = 'no-reply@[' . \long2ip(\ip2long($_SERVER['SERVER_ADDR'])) . ']';
        }

        $message = (new Message())
            ->addTo($recoverInfo['email'], $username)
            ->setSubject('Password Reset')
            ->setFrom($from)
            ->setBody($this->recoveryMessage($token));

        try {
            if (!empty($recoverInfo['gpg_public_key'])) {
                // This will be encrypted with the user's public key:
                $state->gpgMailer->send($message, $recoverInfo['gpg_public_key']);
            } else {
                // This will be sent as-is:
                $state->mailer->send($message);
            }
        } catch (RuntimeException $ex) {
            // Error sending email
            return false;
        } catch (InvalidArgumentException $ex) {
            // Invalid argument supplied
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

        $state = State::instance();
        if (Symmetric::verify(
            $validator . $recoveryInfo['userid'],
            $state->keyring['auth.recovery_key'],
            $recoveryInfo['hashedtoken']
        )) {
            $_SESSION['userid'] = (int) $recoveryInfo['userid'];
            $_SESSION['session_canary'] = $this->acct->createSessionCanary($recoveryInfo['userid']);
            $this->acct->deleteRecoveryToken($selector);
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
            \Airship\ViewFunctions\cabin_url() . 'forgot-password/' . $token . "\n\n" .
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
            $this->storeViewVar(
                'post_response',
                [
                    'message' => \__('Preferences saved successfully.'),
                    'status' => 'success'
                ]
            );
            return true;
        }
        return false;
    }

    /**
     * Make sure the secret exists, then get the GoogleAuth object
     *
     * @param int $userID
     * @return GoogleAuth
     */
    protected function twoFactorPreamble(int $userID = 0): GoogleAuth
    {
        if (!$userID) {
            $userID = $this->getActiveUserId();
        }
        $secret = $this->acct->getTwoFactorSecret($userID);
        if (empty($secret->getString())) {
            if (!$this->acct->resetTwoFactorSecret($userID)) {
                \Airship\redirect($this->airship_cabin_prefix);
            }
            $secret = $this->acct->getTwoFactorSecret($userID);
        }
        return new GoogleAuth(
            $secret->getString(),
            new TOTP(
                0,
                (int) ($this->config('two-factor.period') ?? 30),
                (int) ($this->config('two-factor.length') ?? 6)
            )
        );
    }

    /**
     * Gets [offset, limit] based on configuration
     *
     * @param string $page
     * @param int|null $per_page
     * @return int[]
     */
    protected function getOffsetAndLimit($page = null, ?int $per_page = null)
    {
        if (!$per_page) {
            $per_page = $this->config('user-directory.per-page') ?? 20;
        }
        $page = (int) (
            !empty($page)
                ? $page
                : ($_GET['page'] ?? 0)
        );
        if ($page < 1) {
            $page = 1;
        }
        return [
            ($page - 1) * $per_page,
            $per_page
        ];
    }
}
