<?php
declare(strict_types=1);
namespace Airship\Engine\Bolt;

use Airship\Engine\{
    Gears, Security\Authentication, Security\Permissions, State
};
use \ParagonIE\Halite\Cookie;
use \Airship\Alerts\Security\LongTermAuthAlert;
use \Airship\Alerts\Security\SecurityAlert;
use \Airship\Alerts\Security\UserNotLoggedIn;
use \Psr\Log\LogLevel;

trait Security
{
    public $airship_auth;
    public $airship_cookie;
    public $airship_perms;

    /**
     * After loading the Security bolt in place, configure it
     * 
     */
    public function tightenSecurityBolt()
    {
        static $tightened = false;
        if ($tightened) {
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
        
        $this->airship_cookie = new Cookie(
            $state->keyring['cookie.encrypt_key']
        );
        
        $this->airship_perms = Gears::get('Permissions', $db);
    }

    /**
     * Perform a permissions check
     *
     * @param string $action action label (e.g. 'read')
     * @param string $context_id context regex (in perm_contexts)
     * @param string $cabin (defaults to current cabin)
     * @param integer $user_id (defaults to current user)
     * @return boolean
     */
    public function can(
        string $action,
        string $context_id = '',
        string $cabin = '',
        int $user_id = 0
    ) {
        if (!\property_exists($this, 'airship_perms')) {
            $this->tightenSecurityBolt();
        }
        return $this->airship_perms->can(
            $action,
            $context_id,
            $cabin,
            $user_id
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
        $state = State::instance();
        $uid_idx = $state->universal['session_index']['user_id'];
        if (empty($_SESSION[$uid_idx])) {
            throw new UserNotLoggedIn(
                \trk('errors.security.not_authenticated')
            );
        }
        return (int) $_SESSION[$uid_idx];
    }


    /**
     * Are we currently logged in as an admin?
     * @param integer $user_id (defaults to current user)
     * @return boolean
     */
    public function isSuperUser(int $user_id = 0) {
        if (!\property_exists($this, 'airship_perms')) {
            $this->tightenSecurityBolt();
        }
        if (empty($user_id)) {
            try {
                $user_id = $this->getActiveUserId();
            } catch (SecurityAlert $e) {
                return false;
            }
        }
        return $this->airship_perms->isSuperUser($user_id);
    }
    
    /**
     * Are we logged in to a user account?
     * 
     * @return boolean
     */
    public function isLoggedIn()
    {
        if (!\property_exists($this, 'airship_cookie')) {
            $this->tightenSecurityBolt();
        }
        $state = State::instance();
        
        $uid_idx = $state->universal['session_index']['user_id'];
        $token_idx = $state->universal['cookie_index']['auth_token'];
        if (!empty($_SESSION[$uid_idx])) {
            // We're logged in!
            return true;
        } elseif (isset($_COOKIE[$token_idx])) {
            $token = $this->airship_cookie->fetch($token_idx);
            if (!empty($token)) {
                return $this->doAutoLogin($token, $uid_idx, $token_idx);
            }
        }
        return false;
    }

    /**
     * Let's do an automatic login
     *
     * @param string $token
     * @param string $uid_idx
     * @param string $token_idx
     * @return bool
     */
    protected function doAutoLogin(
        string $token,
        string $uid_idx,
        string $token_idx
    ): bool {
        $state = State::instance();
        try {
            $userid = $this->airship_auth->loginByToken($token);
            // Set session variable
            $_SESSION[$uid_idx] = $userid;

            // Rotate the authentication token:
            $this->airship_cookie->store(
                $token_idx,
                $this->airship_auth->rotateToken($token, $userid),
                \time() + ($state->universal['long-term-auth-expire'] ?? self::DEFAULT_LONGTERMAUTH_EXPIRE),
                '/',
                '',
                false,
                true
            );
            return true;
        } catch (LongTermAuthAlert $e) {
            // Let's wipe our long-term authentication cookies
            $this->airship_cookie->store($token_idx, null);

            // Let's log this incident
            if (\property_exists($this, 'log')) {
                $this->log(
                    $e->getMessage,
                    LogLevel::CRITICAL,
                    [
                        'exception' => \Airship\throwableToArray($e)
                    ]
                );
            } else {
                $state = State::instance();
                $state->logger->log(
                    LogLevel::CRITICAL,
                    $e->getMessage(),
                    [
                        'exception' => \Airship\throwableToArray($e)
                    ]
                );
            }
        }
        return false;
    }

    /**
     * Completely wipe all authentication mechanisms (Session, Cookie)
     */
    public function completeLogOut(): bool
    {
        $state = State::instance();
        $token_idx = $state->universal['cookie_index']['auth_token'];
        $_SESSION = [];
        $this->airship_cookie->store($token_idx, null);
        return \session_regenerate_id(true);
    }
}