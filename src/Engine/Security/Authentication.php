<?php
declare(strict_types=1);
namespace Airship\Engine\Security;

use Airship\Alerts\Security\{
    LongTermAuthAlert,
    SecurityAlert
};
use Airship\Engine\{
    Database,
    Gadgets
};
use Airship\Engine\Security\Migration\WordPress;
use ParagonIE\ConstantTime\{
    Base64,
    Binary
};
use ParagonIE\Halite\{
    HiddenString,
    Password,
    Symmetric\EncryptionKey,
    Util as CryptoUtil
};

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
     * Fot strings that will be base64-encoded, so it's best to keep their lengths
     * a multiple of 3. Get the most entropy out of our encoded bytes. :)
     */
    public const SELECTOR_BYTES = 12;
    public const VALIDATOR_BYTES = 33;
    public const LONG_TERM_AUTH_BYTES = 45;

    /**
     * @var Database
     */
    protected $db;

    /**
     * @var string
     */
    protected $dummyHash;

    /**
     * @var EncryptionKey
     */
    protected $key;

    /**
     * $this->tableConfig contains all of the selectors (table/column names)
     * used in the internal queries. This is useful if you need to change the
     * queries in a Gear without rewriting them.
     *
     * @var array
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
     * This generates as passphrase of 63 random bytes then stores a dummy
     * Argon2i hash in a protected property. When a user isn't found, the
     * supplied password is evaluated against the dummy password. This makes
     * it harder for an attacker to determine valid usernames from the amount
     * of time it takes to return an error message.
     *
     * @param EncryptionKey $key
     * @param Database|null $db
     */
    public function __construct(EncryptionKey $key, ?Database $db)
    {
        $this->key = $key;
        
        // 504 bits of entropy; good luck
        $dummy = new HiddenString(
            Base64::encode(\random_bytes(63))
        );
        $this->dummyHash = Password::hash(
            $dummy,
            $this->key
        );

        $this->db = $db ?? \Airship\get_database();
        $this->registerMigrations();
    }
    
    /**
     * Generate a hash of a password
     * 
     * @param HiddenString $password
     * @return string
     */
    public function createHash(HiddenString $password): string
    {
        return Password::hash($password, $this->key);
    }
    
    /**
     * Create, store, and return a token for long-term authentication
     * 
     * @param int $userId
     * @return HiddenString (to store in a cookie, for example)
     */
    public function createAuthToken(int $userId): HiddenString
    {
        $f = $this->tableConfig['fields']['longterm'];
        
        $selector = \random_bytes(static::SELECTOR_BYTES);
        $validator = \random_bytes(static::VALIDATOR_BYTES);
        
        $this->db->insert(
            $this->tableConfig['table']['longterm'],
            [
                $f['userid'] => $userId,
                $f['selector'] => Base64::encode($selector),
                $f['validator'] => CryptoUtil::hash($validator)
            ]
        );
        return new HiddenString(
            Base64::encode($selector . $validator)
        );
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
        // Let's fetch the user data from the database
        $user = $this->db->row(
            'SELECT * FROM ' . $table . ' WHERE username = ?',
            $username
        );
        if (empty($user)) {
            /**
             * User not found. Use the dummy password to mitigate user
             * enumeration via timing side-channels.
             */
            Password::verify($password, $this->dummyHash, $this->key);
            
            // No matter what, return false here:
            return false;
        }
        if (!empty($user['migration'])) {
            $success = $this->migrateImportedHash(
                $password,
                new HiddenString($user['password']),
                $user
            );
            if ($success) {
                return (int) $user['userid'];
            }
        }

        if (Password::verify($password, $user['password'], $this->key)) {
            return (int) $user['userid'];
        }
        return false;
    }
    
    /**
     * Authenticate a user by a long-term authentication token (e.g. a cookie).
     *
     * We're using a split-token approach here. The first 12 bytes are used in
     * the SELECT query. The remaining 33 bytes are hashed, then compared with
     * the stored hash in constant-time. There is no useful timing leak, even
     * if the database leaks like a sieve.
     * 
     * @param HiddenString $token
     * @return mixed int
     * @throws LongTermAuthAlert
     */
    public function loginByToken(HiddenString $token): int
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

        try {
            /**
             * @var string
             */
            $decoded = Base64::decode($token->getString());
        } catch (\RangeException $ex) {
            throw new LongTermAuthAlert(
                \trk('errors.security.invalid_persistent_token')
            );
        }

        // We need a raw binary string of the correct length
        if (!\is_string($decoded)) {
            throw new LongTermAuthAlert(
                \trk('errors.security.invalid_persistent_token')
            );
        } elseif (Binary::safeStrlen($decoded) !== self::LONG_TERM_AUTH_BYTES) {
            throw new LongTermAuthAlert(
                \trk('errors.security.invalid_persistent_token')
            );
        }
        unset($token); // HiddenString uses memzero internally.

        // Split the selector from the validator (which is then hashed)
        $sel = Binary::safeSubstr($decoded, 0, self::SELECTOR_BYTES);
        $val = CryptoUtil::raw_hash(
            Binary::safeSubstr($decoded, self::SELECTOR_BYTES)
        );
        \Sodium\memzero($decoded);
        
        $record = $this->db->row(
            'SELECT * FROM ' . $table . ' WHERE ' . $f['selector'] . ' = ?',
            Base64::encode($sel)
        );
        if (empty($record)) {
            \Sodium\memzero($val);
            throw new LongTermAuthAlert(
                \trk('errors.security.invalid_persistent_token')
            );
        }
        $stored = \Sodium\hex2bin($record[$f['validator']]);
        \Sodium\memzero($record[$f['validator']]);

        if (!\hash_equals($stored, $val)) {
            \Sodium\memzero($val);
            \Sodium\memzero($stored);
            throw new LongTermAuthAlert(
                \trk('errors.security.invalid_persistent_token')
            );
        }
        \Sodium\memzero($stored);
        \Sodium\memzero($val);

        $userID = (int) $record[$f['userid']];

        // Important: Set the session canary. Prevents login/logout cycles.
        $_SESSION['session_canary'] = $this->db->cell(
            'SELECT session_canary FROM airship_users WHERE userid = ?',
            $userID
        );
        return $userID;
    }

    /**
     * Attempt to login against a migrated hash. If successful,
     * replace the existing password hash with an encrypted hash
     * of the original password.
     *
     * @param HiddenString $password
     * @param HiddenString $passwordHash
     * @param array $userData
     * @return bool
     * @throws SecurityAlert
     */
    public function migrateImportedHash(
        HiddenString $password,
        HiddenString $passwordHash,
        array $userData = []
    ): bool {
        if (!isset($userData['migration']['type'])) {
            throw new SecurityAlert(
                \__('No migration type registered.')
            );
        }
        $migration = Gadgets::loadMigration(
            $userData['migration']['type']
        );
        $migration->setPasswordKey($this->key);

        $table = $this->db->escapeIdentifier(
            $this->tableConfig['table']['accounts']
        );

        if ($migration->validate($password, $passwordHash, $userData['migration'])) {
            $this->db->beginTransaction();
            // We now know the plaintext. Let's replace their password.
            $this->db->update(
                $table,
                [
                    'password' => Password::hash(
                        $password,
                        $this->key
                    ),
                    'migration' =>
                        null
                ],
                [
                    'userId' =>
                        $userData['userid']
                ]
            );
            return $this->db->commit();
        }
        return false;
    }

    /**
     * Replace the existing long-term authentication cookie
     *
     * @param HiddenString $token
     * @param int $userId
     * @return bool|HiddenString
     */
    public function rotateToken(HiddenString $token, int $userId = 0)
    {
        try {
            /**
             * @var string
             */
            $decoded = Base64::decode($token->getString());
        } catch (\RangeException $ex) {
            return false;
        }
        if (!\is_string($decoded)) {
            return false;
        } elseif (Binary::safeStrlen($decoded) !== self::LONG_TERM_AUTH_BYTES) {
            return false;
        }
        $sel = Binary::safeSubstr($decoded, 0, self::SELECTOR_BYTES);
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
     * @param Database $db
     * @return self
     */
    public function setDatabase(Database $db): self
    {
        $this->db = $db;
        return $this;
    }
    
    /**
     * Set the database of this authentication library to match this 
     * 
     * @param string $dbIndex
     * @return self
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
     * @return self
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
     * @return self
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
     * @return self
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
     * @return self
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
     * @return self
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
     * @return self
     */
    public function setUsernameField(string $field): self
    {
        $this->tableConfig['field']['accounts']['username'] = $field;
        return $this;
    }

    /**
     * Overloadable.
     *
     * @return void
     */
    protected function registerMigrations(): void
    {
        Gadgets::registerMigration(WordPress::TYPE, new WordPress());
    }
}
