<?php
declare(strict_types=1);
namespace Airship\Engine\Bolt;

use Airship\Alerts\{
    Security\SecurityAlert,
    Security\LongTermAuthAlert,
    Security\UserNotLoggedIn
};
use Airship\Engine\{
    AutoPilot, Database, Gears, Controller, Model, Security\Authentication, Security\Permissions, State, View
};
use ParagonIE\Cookie\{
    Cookie,
    Session
};
use ParagonIE\Halite\HiddenString;
use ParagonIE\Halite\Symmetric\Crypto as Symmetric;
use Psr\Log\LogLevel;

/**
 * Bolt Security
 *
 * Common security features. Mostly access controls.
 *
 * @package Airship\Engine\Bolt
 */
trait Security
{
    /**
     * @var Authentication
     */
    public $airship_auth = null;

    /**
     * @var Permissions
     */
    public $airship_perms = null;

    /**
     * After loading the Security bolt in place, configure it.
     *
     * @return void
     */
    public function tightenSecurityBolt(): void
    {
        static $tightened = false;
        if ($tightened) {
            // This was already run once.
            return;
        }
        $state = State::instance();
        $db = isset($this->db)
            ? $this->db
            : \Airship\get_database();

        $this->airship_auth = Gears::get(
            'Authentication',
            $state->keyring['auth.password_key'],
            $db
        );

        $this->airship_perms = Gears::get('Permissions', $db);
    }

    /**
     * Perform a permissions check
     *
     * @param string $action action label (e.g. 'read')
     * @param string $context context regex (in perm_contexts)
     * @param string $cabin (defaults to current cabin)
     * @param integer $userID (defaults to current user)
     * @return bool
     */
    public function can(
        string $action,
        string $context = '',
        string $cabin = '',
        int $userID = 0
    ): bool {
        if (!($this->airship_perms instanceof Permissions)) {
            $this->tightenSecurityBolt();
        }
        return $this->airship_perms->can(
            $action,
            $context,
            $cabin,
            $userID
        );
    }

    /**
     * Get the current user ID. Throws a UserNotLoggedIn exception if you aren't logged in.
     *
     * @return int
     * @throws UserNotLoggedIn
     */
    public function getActiveUserId(): int
    {
        if (empty($_SESSION['userid'])) {
            throw new UserNotLoggedIn(
                \trk('errors.security.not_authenticated')
            );
        }
        return (int) $_SESSION['userid'];
    }


    /**
     * Are we currently logged in as an admin?
     *
     * @param integer $userId (defaults to current user)
     * @return bool
     */
    public function isSuperUser(int $userId = 0): bool
    {
        if (!($this->airship_perms instanceof Permissions)) {
            $this->tightenSecurityBolt();
        }
        if (empty($userId)) {
            try {
                $userId = $this->getActiveUserId();
            } catch (SecurityAlert $e) {
                return false;
            }
        }
        return $this->airship_perms->isSuperUser($userId);
    }
    
    /**
     * Are we logged in to a user account?
     * 
     * @return bool
     */
    public function isLoggedIn(): bool
    {
        if (!($this->airship_auth instanceof Authentication)) {
            $this->tightenSecurityBolt();
        }
        $state = State::instance();
        if (!empty($_SESSION['userid'])) {
            // We're logged in!
            if ($this->config('password-reset.logout')) {
                return $this->verifySessionCanary($_SESSION['userid']);
            }
            return true;
        } elseif (isset($_COOKIE['airship_token'])) {
            // We're not logged in, but we have a long-term
            // authentication token, so we should do an automatic
            // login and, if successful, respond affirmatively.
            $token = Symmetric::decrypt(
                $_COOKIE['airship_token'],
                $state->keyring['cookie.encrypt_key']
            );
            if (!empty($token)) {
                return $this->doAutoLogin($token, 'userid', 'airship_token');
            }
        }
        return false;
    }

    /**
     * Let's do an automatic login
     *
     * @param HiddenString $token
     * @param string $uid_idx
     * @param string $token_idx
     * @return bool
     * @throws LongTermAuthAlert (only in debug mode)
     * @throws \TypeError
     */
    protected function doAutoLogin(
        HiddenString $token,
        string $uid_idx,
        string $token_idx
    ): bool {
        if (!($this->airship_auth instanceof Authentication)) {
            $this->tightenSecurityBolt();
        }
        $state = State::instance();
        try {
            $userId = $this->airship_auth->loginByToken($token);

            if (!$this->verifySessionCanary($userId, false)) {
                unset($token);
                return false;
            }

            // Regenerate session ID:
            Session::regenerate(true);

            // Set session variable
            $_SESSION[$uid_idx] = $userId;

            /**
             * @var AutoPilot
             */
            $autoPilot = Gears::getName('AutoPilot');
            if (IDE_HACKS) {
                // We're using getName(), this is just to fool IDEs.
                $autoPilot = new AutoPilot([], new View(new \Twig_Environment()));
            }
            $httpsOnly = (bool) $autoPilot::isHTTPSConnection();

            // Rotate the authentication token:
            Cookie::setcookie(
                $token_idx,
                Symmetric::encrypt(
                    new HiddenString(
                        $this->airship_auth->rotateToken($token, $userId)
                    ),
                    $state->keyring['cookie.encrypt_key']
                ),
                \time() + ($state->universal['long-term-auth-expire'] ?? 2592000),
                '/',
                '',
                $httpsOnly ?? false,
                true
            );
            unset($token);
            return true;
        } catch (LongTermAuthAlert $e) {
            $state = State::instance();
            // Let's wipe our long-term authentication cookies
            Cookie::setcookie(
                $token_idx,
                null,
                0,
                '/',
                '',
                $httpsOnly ?? false,
                true
            );

            // Let's log this incident
            if (\property_exists($this, 'log')) {
                $this->log(
                    $e->getMessage(),
                    LogLevel::CRITICAL,
                    [
                        'exception' => \Airship\throwableToArray($e)
                    ]
                );
            } else {
                $state->logger->log(
                    LogLevel::CRITICAL,
                    $e->getMessage(),
                    [
                        'exception' => \Airship\throwableToArray($e)
                    ]
                );
            }

            // In debug mode, re-throw the exception:
            if ($state->universal['debug']) {
                throw $e;
            }
        }
        return false;
    }

    /**
     * Completely wipe all authentication mechanisms (Session, Cookie)
     *
     * @return bool
     */
    public function completeLogOut(): bool
    {
        if (!($this->airship_auth instanceof Authentication)) {
            $this->tightenSecurityBolt();
        }
        $_SESSION = [];
        Cookie::setcookie('airship_token', null);
        Session::regenerate(true);
        return true;
    }

    /**
     * If another session triggered a password reset, we should be logged out
     * as per the Bridge configuration. (This /is/ an optional feature.)
     *
     * @param int $userID
     * @param bool $logOut
     * @return bool
     */
    public function verifySessionCanary(int $userID, bool $logOut = true): bool
    {
        if (empty($_SESSION['session_canary'])) {
            return false;
        }
        $db = \Airship\get_database();
        $canary = $db->cell(
            'SELECT session_canary FROM airship_users WHERE userid = ?',
            $userID
        );
        if (empty($canary)) {
            $this->log(
                'No session canary was registered with this user in the database.',
                LogLevel::DEBUG,
                [
                    'database' => $canary,
                    'session' => $_SESSION['session_canary']
                ]
            );
            $this->completeLogOut();
            return false;
        }
        if (!\hash_equals($canary, $_SESSION['session_canary'])) {
            $this->log(
                'User was logged out for having the wrong canary.',
                LogLevel::DEBUG,
                [
                    'expected' => $canary,
                    'possessed' => $_SESSION['session_canary']
                ]
            );
            if ($logOut) {
                $this->completeLogOut();
            }
            return false;
        }
        return true;
    }
}
