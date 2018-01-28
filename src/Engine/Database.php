<?php
declare(strict_types=1);
namespace Airship\Engine;

use Airship\Alerts\Database as DBAlert;
use Airship\Engine\Contract\DBInterface;
use Airship\Engine\Security\Util;
use ParagonIE\EasyDB\EasyDB;

/**
 * Class Database
 *
 * Wraps PDO and gives a bunch of nice and easy one-liners
 * that in most cases use Prepared Statements to ensure
 * we aren't committing a huge security foot-cannon.
 *
 * @package Airship\Engine
 */
class Database extends EasyDB implements DBInterface
{
    /**
     * @var string
     */
    protected $dbengine = '';

    /**
     * @var \PDO
     */
    protected $pdo;
    
    /**
     * Dependency-Injectable constructor
     * 
     * @param \PDO $pdo
     * @param string $dbEngine
     * @throws DBAlert\DBException
     */
    public function __construct(\PDO $pdo = null, $dbEngine = '')
    {
        if (!$pdo) {
            throw new DBAlert\DBException(
                \__(
                    'An instance of PDO was expected. ' .
                    'This parameter only defaults to NULL for unit testing purposes.'
                )
            );
        }
        if (empty($dbEngine)) {
            $dbEngine = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        }
        $this->dbengine = $dbEngine;
        parent::__construct($pdo);
    }

    /**
     * Create a new Database object based on PDO constructors
     * 
     * @param string|array $dsn
     * @param string $username
     * @param string $password
     * @param array $options
     * @return Database
     * @throws DBAlert\DBException
     * @throws \TypeError
     */
    public static function factory(
        $dsn,
        string $username = '',
        string $password = '',
        $options = []
    ): Database {
        $dbEngine = '';
        if (\is_array($dsn)) {
            list ($dsn, $dbEngine, $username, $password) = self::flattenDSN(
                $dsn,
                $username,
                $password
            );
        } elseif (\gettype($dsn) !== 'string') {
            throw new \TypeError(
                \__('DSN must be string or array')
            );
        } elseif (\strpos((string) $dsn, ':') !== false) {
            $dbEngine = \explode(':', $dsn)[0];
        }

        // Database engine specific DSN addendums
        switch ($dbEngine) {
            case 'mysql':
                if (\strpos($dsn, ';charset=') === false) {
                    // If no charset is specified, default to UTF-8
                    $dsn .= ';charset=utf8';
                }
                break;
        }

        try {
            if (empty($username) && empty($password) && empty($options)) {
                $pdo = new \PDO($dsn);
            } else {
                $pdo = new \PDO($dsn, $username, $password, $options);
            }
        } catch (\PDOException $e) {
            // Don't leak the DB password in a stack trace:
            throw new DBAlert\DBException(
                \trk('errors.database.pdo_exception')
            );
        }
        if (!isset($pdo)) {
            throw new DBAlert\DBException(
                \trk('errors.database.pdo_exception')
            );
        }

        // Let's turn off emulated prepares
        $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        
        return new Database($pdo, $dbEngine);
    }

    /**
     * Flatten an array into a DSN string and driver
     *
     * @param array $dbConf
     * @param string $username
     * @param string $password
     * @return array [$dsn, $driver]
     * @throws DBAlert\DBException
     *
     * @return array
     * @throws DBAlert\DBException
     * @throws \TypeError
     */
    public static function flattenDSN(
        array $dbConf,
        string $username = '',
        string $password = ''
    ): array {
        switch ($dbConf['driver']) {
            case 'mysql':
                $dsn = $dbConf['driver'].':';
                if (Util::subString($dbConf['host'], 0, 5) === 'unix:') {
                    $dsn .= 'unix_socket=' . Util::subString($dbConf['host'], 5) . ';';
                } else {
                    $dsn .= 'host=' . $dbConf['host'] . ';';
                }
                if (!empty($dbConf['port'])) {
                    $dsn .= 'port=' . $dbConf['port'] . ';';
                }
                $dsn .= 'dbname=' . $dbConf['database'];
                return [
                    $dsn,
                    $dbConf['driver'],
                    $dbConf['username'] ?? $username,
                    $dbConf['password'] ?? $password
                ];

            case 'pgsql':
                $dsn = $dbConf['driver'].':';
                if (isset($dbConf['host'])) {
                    if (Util::subString($dbConf['host'], 0, 5) === 'unix:') {
                        $dsn .= 'unix_socket=' . Util::subString($dbConf['host'], 5) . ';';
                    } else {
                        $dsn .= 'host=' . $dbConf['host'] . ';';
                    }
                }
                if (!empty($dbConf['port'])) {
                    $dsn .= 'port=' . $dbConf['port'] . ';';
                }
                $dsn .= 'dbname='.$dbConf['database'];
                return [
                    $dsn,
                    $dbConf['driver'],
                    $dbConf['username'] ?? $username,
                    $dbConf['password'] ?? $password
                ];

            case 'sqlite':
                $dsn = $dbConf['driver'].':';
                if (isset($dbConf['path'])) {
                    $dsn .= $dbConf['path'];
                } else {
                    $dsn .= ':memory:';
                }
                return [$dsn, $dbConf['driver'], null, null];

            default:
                throw new DBAlert\DBException(
                    \trk('errors.database.not_implemented', (string) $dbConf['driver'])
                );
        }
    }
}
