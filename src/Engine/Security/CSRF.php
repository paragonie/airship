<?php
declare(strict_types=1);
namespace Airship\Engine\Security;

use \Airship\Alerts\Security\CSRF\InvalidConfig;
use \ParagonIE\ConstantTime\Base64UrlSafe;
use \ParagonIE\Halite\Util as CryptoUtil;

/**
 * Class CSRF
 * @package Airship\Engine\Security
 */
class CSRF
{
    const FORM_TOKEN = '_CSRF_TOKEN';

    /**
     * @var int
     */
    protected $recycleAfter = 1024;

    /**
     * @var bool Default to FALSE to be friendly to Tor/Mobile users
     */
    protected $hmacIP = false;

    /**
     * @var bool
     */
    protected $expireOld = false;

    /**
     * @var string
     */
    protected $sessionIndex = 'CSRF';

    /**
     * CSRF constructor.
     *
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->reconfigure($options);
    }

    /**
     * Insert a CSRF token to a form
     *
     * @param string $lockTo This CSRF token is only valid for this HTTP request endpoint
     * @param bool $echo if true, echo instead of returning
     * @return string
     */
    public function insertToken(
        string $lockTo = '',
        bool $echo = true
    ): string {
        $ret = '<input type="hidden"' .
                ' name="' . Util::noHTML(self::FORM_TOKEN) . '"' .
                ' value="' . $this->getTokenString($lockTo) . '"' .
            ' />';
        if ($echo) {
            echo $ret;
            return '';
        }
        return $ret;
    }

    /**
     * Retrieve a token array for unit testing endpoints
     *
     * @param string $lockTo - Only get tokens locked to a particular form
     * 
     * @return string
     */
    public function getTokenString(string $lockTo = ''): string
    {
        if (!isset($_SESSION[$this->sessionIndex])) {
            $_SESSION[$this->sessionIndex] = [];
        }

        if (empty($lockTo)) {
            $lockTo = $_SERVER['REQUEST_URI'] ?? '/';
        }

        if (\preg_match('#/$#', $lockTo)) {
            $lockTo = \substr($lockTo, 0, strlen($lockTo) - 1);
        }

        list($index, $token) = $this->generateToken($lockTo);

        if ($this->hmacIP) {
            // Use a keyed BLAKE2b hash to only allow this particular IP to send this request
            $token = Base64UrlSafe::encode(
                \Sodium\crypto_generichash(
                    $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                    Base64UrlSafe::decode($token),
                    \Sodium\CRYPTO_GENERICHASH_BYTES
                )
            );
        }

        return $index.':'.$token;
    }

    /**
     * Validate a request based on $_SESSION and $_POST data
     *
     * @return bool
     */
    public function check(): bool
    {
        if (!isset($_SESSION[$this->sessionIndex])) {
            // We don't even have a session array initialized
            $_SESSION[$this->sessionIndex] = [];
            return false;
        }
        
        if (!isset($_POST[self::FORM_TOKEN]) || !\is_string($_POST[self::FORM_TOKEN])) {
            return false;
        }

        // Let's pull the POST data
        list ($index, $token) = explode(':', $_POST[self::FORM_TOKEN]);
        
        if (empty($index) || empty($token)) {
            return false;
        }

        if (!isset($_SESSION[$this->sessionIndex][$index])) {
            // CSRF Token not found
            return false;
        }

        // Grab the value stored at $index
        $stored = $_SESSION[$this->sessionIndex][$index];

        // We don't need this anymore
        unset($_SESSION[$this->sessionIndex][$index]);

        // Which form action="" is this token locked to?
        $lockTo = $_SERVER['REQUEST_URI'];
        if (\preg_match('#/$#', $lockTo)) {
            // Trailing slashes are to be ignored
            $lockTo = substr($lockTo, 0, strlen($lockTo) - 1);
        }

        if (!empty($stored['lockto'])) {
            if (!\hash_equals($lockTo, $stored['lockto'])) {
                // Form target did not match the request this token is locked to!
                return false;
            }
        }

        // This is the expected token value
        if ($this->hmacIP === false) {
            // We just stored it wholesale
            $expected = $stored['token'];
        } else {
            // We mixed in the client IP address to generate the output
            $expected = Base64UrlSafe::encode(
                CryptoUtil::raw_keyed_hash(
                    $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                    Base64UrlSafe::decode($stored['token'])
                )
            );
        }
        return \hash_equals($token, $expected);
    }

    /**
     * Use this to change the configuration settings.
     * Only use this if you know what you are doing.
     *
     * @param array $options
     * @throws InvalidConfig
     */
    public function reconfigure(array $options = [])
    {
        foreach ($options as $opt => $val) {
            switch ($opt) {
                case 'recycleAfter':
                case 'hmacIP':
                case 'expireOld':
                case 'sessionIndex':
                    $this->{$opt} = $val;
                    break;
                default:
                    throw new InvalidConfig(
                        \trk('errors.object.invalid_property', $opt, __CLASS__)
                    );
            }
        }
    }

    /**
     * Generate, store, and return the index and token
     *
     * @param string $lockTo What URI endpoint this is valid for
     * @return array [string, string]
     */
    protected function generateToken(string $lockTo = ''): array
    {
        // Create a distinct index:
        do {
            $index = Base64UrlSafe::encode(
                \random_bytes(18)
            );
        } while (isset($_SESSION[$this->sessionIndex][$index]));
        $token = Base64UrlSafe::encode(\random_bytes(33));

        $_SESSION[$this->sessionIndex][$index] = [
            'created' => \intval(\date('YmdHis')),
            'uri' => isset($_SERVER['REQUEST_URI'])
                ? $_SERVER['REQUEST_URI']
                : $_SERVER['SCRIPT_NAME'],
            'token' => $token
        ];

        if (!empty($lockTo)) {
            // Get rid of trailing slashes.
            if (\preg_match('#/$#', $lockTo)) {
                $lockTo = Util::subString($lockTo, 0, Util::stringLength($lockTo) - 1);
            }
            $_SESSION[$this->sessionIndex][$index]['lockto'] = $lockTo;
        }

        $this->recycleTokens();
        return [$index, $token];
    }

    /**
     * Enforce an upper limit on the number of tokens stored in session state
     * by removing the oldest tokens first.
     */
    protected function recycleTokens()
    {
        if (!$this->expireOld) {
            // This is turned off.
            return;
        }
        // Sort by creation time
        \uasort(
            $_SESSION[$this->sessionIndex],
            function (array $a, array $b) {
                return $a['created'] <=> $b['created'];
            }
        );

        if (\count($_SESSION[$this->sessionIndex]) > $this->recycleAfter) {
            // Let's knock off the oldest one
            \array_shift($_SESSION[$this->sessionIndex]);
        }
    }
}
