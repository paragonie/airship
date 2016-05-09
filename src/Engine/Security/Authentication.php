<?php
declare(strict_types=1);
namespace Airship\Engine\Security;

use \Airship\Alerts\Security\LongTermAuthAlert;
use \Airship\Engine\Contract\DBInterface;
use \Airship\Engine\Database;
use \ParagonIE\Halite\{
    Symmetric\EncryptionKey,
    Password
};
use \ParagonIE\ConstantTime\Base64;

/**
 * Class Authentication
 *
 * Airship gear: Authentication
 *
 * Covers both short-term authentication (current browsing session) and optional
 * long-term authentication (i.e. "remember me" cookies).
 *
 * @ref https://paragonie.com/b/rgbsTmsWFoQRpIg-
 * @package Airship\Engine\Security
 */
class Authentication
{
    /**
     * These values will be base64-encoded, so it's best to keep them a multiple
     * of 3. Get the most entropy out of our encoded bytes. :)
     */
    const SELECTOR_BYTES = 12;
    const VALIDATOR_BYTES = 33;
    
    protected $db;
    protected $dummyHash;
    /**
     * $this->tableConfig contains all of the selectors (table/column names)
     * used in the internal queries. This is useful if you need to change the
     * queries in a Gear without rewriting them.
     */
    protected $tableConfig = [
        'table' => [
            'accounts' => 'airship_users',
            'longterm' => 'airship_auth_tokens'
        ],
        'fields' => [
            'accounts' => [
                'userid'   => 'userid',
                'username' => 'username',
                'password' => 'password'
            ],
            'longterm' => [
                'tokenid' => 'tokenid',
                'userid' => 'userid',
                'selector' => 'selector',
                'validator' => 'validator'
            ]
        ]
    ];

    /**
     * Authentication constructor.
     *
     * @param EncryptionKey $key
     * @param DBInterface|null $db
     */
    public function __construct(EncryptionKey $key, DBInterface $db = null)
    {
        $this->key = $key;
        
        // 504 bits of entropy; good luck
        $dummy = Base64::encode(\random_bytes(63));
        $this->dummyHash = Password::hash($dummy, $this->key);
        
        if (!empty($db)) {
            $this->db = $db;
        }
        if (IDE_HACKS) {
            $this->db = new Database(new \PDO('sqlite::memory:', 'sqlite'));
        }
    }
    
    /**
     * Generate a hash of a password
     * 
     * @param HiddenString $password
     * @return string
     */
    public function createHash(HiddenString $password): string
    {
        return Password::hash($password->getString(), $this->key);
    }
    
    /**
     * Create, store, and return a token for long-term authentication
     * 
     * @param int $userId
     * @return string (to store in a cookie, for example)
     */
    public function createAuthToken(int $userId): string
    {
        $f = $this->tableConfig['fields']['longterm'];
        
        $selector = \random_bytes(self::SELECTOR_BYTES);
        $validator = \random_bytes(self::VALIDATOR_BYTES);
        
        $this->db->insert(
            $this->tableConfig['table']['longterm'],
            [
                $f['userid'] => $userId,
                $f['selector'] => Base64::encode($selector),
                $f['validator'] => \Sodium\bin2hex(
                    \Sodium\crypto_generichash($validator)
                )
            ]
        );
        return Base64::encode($selector . $validator);
    }
    
    /**
     * Verifies that the password is valid for a given user account. Returns
     * false whether or not the user name is valid and attempts to minimize
     * leaking that information through timing side-channels.
     * 
     * @param string $username
     * @param HiddenString $password
     * @return bool|int
     */
    public function login(string $username, HiddenString $password)
    {
        /**
         * To prevent extreme stupidity, we escape our table and column names
         * here. We shouldn't ever *need* to do this, but as long as developers
         * are creative, they will find creative ways to make their apps
         * insecure and we should anticipate them as much as we can.
         */
        $table = $this->db->escapeIdentifier(
            $this->tableConfig['table']['accounts']
        );

        $f = [
            'userid' => $this->tableConfig['fields']['accounts']['userid'],
            'username' => $this->tableConfig['fields']['accounts']['username'],
            'password' => $this->tableConfig['fields']['accounts']['password']
        ];
        
        // Let's fetch the user data from the database
        $user = $this->db->row(
            'SELECT * FROM '.$table.' WHERE '.$f['username'].' = ?',
            $username
        );
        if (empty($user)) {
            /**
             * User not found. Use the dummy password to mitigate user
             * enumeration via timing side-channels.
             */
            Password::verify($password->getString(), $this->dummyHash, $this->key);
            
            // No matter what, return false here:
            return false;
        } elseif (Password::verify($password->getString(), $user[$f['password']], $this->key)) {
            return $user[$f['userid']];
        }
        return false;
    }
    
    /**
     * Authenticate a user by a long-term authentication token (e.g. a cookie).
     * 
     * @param string $token = '
     * @return mixed int (success) or FALSE (failure)
     * @throws LongTermAuthAlert
     */
    public function loginByToken(string $token = '')
    {
        $table = $this->db->escapeIdentifier(
            $this->tableConfig['table']['longterm']
        );
        $f = [
            'selector' => $this->db->escapeIdentifier(
                $this->tableConfig['fields']['longterm']['selector']
            ),
            'userid' => $this->tableConfig['fields']['longterm']['userid'],
            'validator' => $this->tableConfig['fields']['longterm']['validator']
        ];

        $decoded = Base64::decode($token);
        if ($decoded === false) {
            return false;
        } elseif (
            \mb_strlen($decoded, '8bit')
                !== 
            (self::SELECTOR_BYTES + self::VALIDATOR_BYTES)
        ) {
            return false;
        }
        $sel = \mb_substr($decoded, 0, self::SELECTOR_BYTES, '8bit');
        $val = \Sodium\crypto_generichash(
            \mb_substr($decoded, self::SELECTOR_BYTES, null, '8bit')
        );
        \Sodium\memzero($decoded);
        
        $record = $this->db->row(
            'SELECT * FROM '.$table.' WHERE '.$f['selector'].' = ?',
            Base64::encode($sel)
        );
        if (empty($record)) {
            throw new LongTermAuthAlert(
                \trk('errors.security.invalid_persistent_token')
            );
        }
        $stored = \Sodium\hex2bin($record[$f['validator']]);
        if (!\hash_equals($stored, $val)) {
            throw new LongTermAuthAlert(
                \trk('errors.security.invalid_persistent_token')
            );
        }
        return (int) $record[$f['userid']];
    }

    /**
     * Replace the existing long-term authentication cookie
     *
     * @param string $token
     * @param int $userId
     * @return mixed
     */
    public function rotateToken(string $token, int $userId = 0)
    {
        $decoded = Base64::decode($token);
        if ($decoded === false) {
            return false;
        } elseif (
            \mb_strlen($decoded, '8bit')
                !==
            (self::SELECTOR_BYTES + self::VALIDATOR_BYTES)
        ) {
            return false;
        }
        $sel = \mb_substr($decoded, 0, self::SELECTOR_BYTES, '8bit');
        \Sodium\memzero($decoded);

        // Delete the old token
        $this->db->delete(
            $this->tableConfig['table']['longterm'],
            [
                $this->tableConfig['fields']['longterm']['selector'] =>
                    Base64::encode($sel)
            ]
        );

        // Let's get a new token
        return $this->createAuthToken($userId);
    }
    
    /**
     * Sets the database handler.
     * 
     * @param DBInterface $db
     * @return Authentication ($this)
     */
    public function setDatabase(DBInterface $db): self
    {
        $this->db = $db;
        return $this;
    }
    
    /**
     * Set the database of this authentication library to match this 
     * 
     * @param string $dbIndex
     * @return Authentication ($this)
     */
    public function setDatabaseByKey(string $dbIndex = ''): self
    {
        $this->db = \Airship\get_database($dbIndex);
        return $this;
    }
    
    /**
     * Sets the column name used to reference the "selector" component of the
     * long-term authentication token.
     * 
     * @param string $field
     * @return Authentication ($this)
     */
    public function setLongTermSelectorField(string $field): self
    {
        $this->tableConfig['field']['longterm']['selector'] = $field;
        return $this;
    }
    
    /**
     * Sets the column name used to reference the "validator" component of the
     * long-term authentication token.
     * 
     * @param string $field
     * @return Authentication ($this)
     */
    public function setLongTermValidatorField(string $field): self
    {
        $this->tableConfig['field']['longterm']['validator'] = $field;
        return $this;
    }
    
    
    /**
     * Sets the column name used to reference the password hash stored in the
     * database, for SQL queries.
     * 
     * @param string $field
     * @return Authentication ($this)
     */
    public function setPasswordField(string $field): self
    {
        $this->tableConfig['field']['accounts']['password'] = $field;
        return $this;
    }
    
    /**
     * Change the table used for 
     * 
     * @param string $table
     * @return Authentication ($this)
     */
    public function setTable(string $table): self
    {
        $this->tableConfig['table'] = $table;
        return $this;
    }
    
    /**
     * Sets the column name used to reference the primary key (userid)
     * 
     * @param string $field
     * @return Authentication ($this)
     */
    public function setUserIdField(string $field): self
    {
        $this->tableConfig['field']['accounts']['userid'] = $field;
        return $this;
    }
    
    /**
     * Sets the column name used to reference the user selector, for SQL 
     * queries.
     * 
     * @param string $field
     * @return Authentication ($this)
     */
    public function setUsernameField(string $field): self
    {
        $this->tableConfig['field']['accounts']['username'] = $field;
        return $this;
    }
}
