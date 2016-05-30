<?php
declare(strict_types=1);
namespace Airship\Engine;

use \Airship\Alerts\Database as DBAlert;
use \Airship\Engine\Contract\DBInterface;

/**
 * Class Database
 *
 * Wraps PDO and gives a bunch of nice and easy one-liners
 * that in most cases use Prepared Statements to ensure
 * we aren't committing a huge security foot-cannon.
 *
 * @package Airship\Engine
 */
class Database implements DBInterface
{
    protected $dbengine = null;
    protected $pdo = null;
    
    /**
     * Dependency-Injectable constructor
     * 
     * @param \PDO $pdo
     * @param string $dbengine
     */
    public function __construct(\PDO $pdo, $dbengine = '')
    {
        $this->pdo = $pdo;
        $this->dbengine = $dbengine;
        $this->pdo->setAttribute(
            \PDO::ATTR_EMULATE_PREPARES,
            false
        );
        $this->pdo->setAttribute(
            \PDO::ATTR_ERRMODE,
            \PDO::ERRMODE_EXCEPTION
        );
    }
    
    /**
     * Create a new Database object based on PDO constructors
     * 
     * @param string $dsn
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
        $post_query = null;
        $dbEngine = '';

        // Let's grab the DB engine
        
        if (\is_array($dsn)) {
            list ($dsn, $dbEngine, $username, $password) = self::flattenDSN($dsn, $username, $password);
        } elseif (!\is_string($dsn)) {
            throw new \TypeError('DSN must be string or array');
        } elseif (strpos($dsn, ':') !== false) {
            $dbEngine = explode(':', $dsn)[0];
        }

        // If no charset is specified, default to UTF-8
        switch ($dbEngine) {
            case 'mysql':
                if (strpos($dsn, ';charset=') === false) {
                    $dsn .= ';charset=utf8';
                }
                break;
        }
        
        try {
            $pdo = new \PDO($dsn, $username, $password, $options);
        } catch (\PDOException $e) {
            throw new DBAlert\DBException(
                \trk('errors.database.pdo_exception')
            );
        }

        // Let's turn off emulated prepares
        $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        if (!empty($post_query)) {
            $pdo->query($post_query);
        }
        
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
     */
    public static function flattenDSN(
        array $dbConf,
        string $username = '',
        string $password = ''
    ): array {
        switch ($dbConf['driver']) {
            case 'mysql':
                $dsn = $dbConf['driver'].':';
                if (isset($dbConf['host'])) {
                    $dsn .= 'host='.$dbConf['host'].';';
                }
                if (isset($dbConf['port'])) {
                    $dsn .= 'port='.$dbConf['port'].';';
                }
                $dsn .= 'dbname='.$dbConf['database'];
                return [
                    $dsn,
                    $dbConf['driver'],
                    $dbConf['username'] ?? $username,
                    $dbConf['password'] ?? $password
                ];

            case 'pgsql':
                $dsn = $dbConf['driver'].':';
                if (isset($dbConf['host'])) {
                    $dsn .= 'host='.$dbConf['host'].';';
                }
                if (isset($dbConf['port'])) {
                    $dsn .= 'port='.$dbConf['port'].';';
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
    
    /**
     * Variadic version of $this->column()
     *
     * @param string $statement SQL query without user data
     * @param int $offset - How many columns from the left are we grabbing from each row?
     * @param mixed ...$params Parameters
     * @return mixed
     */
    public function col(string $statement, int $offset = 0, ...$params)
    {
        return $this->column($statement, $params, $offset);
    }
    
    /**
     * Fetch a column
     * 
     * @param string $statement SQL query without user data
     * @param array $params Parameters
     * @param int $offset - How many columns from the left are we grabbing from each row?
     * @return mixed
     * @throws DBAlert\QueryError
     * @throws \TypeError
     */
    public function column(string $statement, array $params = [], int $offset = 0)
    {
        try {
            if (empty($params)) {
                $stmt = $this->pdo->query($statement, \PDO::FETCH_COLUMN, $offset);
                if ($stmt !== false) {
                    return $stmt->fetchAll(\PDO::FETCH_NUM);
                }
                return false;
            } else {
                if (!\is1DArray($params)) {
                    throw new \TypeError(
                        \trk('errors.database.array_passed')
                    );
                }
                $stmt = $this->pdo->prepare($statement);
                if ($stmt->execute($params) !== false) {
                    return $stmt->fetchAll(\PDO::FETCH_COLUMN, $offset);
                }
            }
            return false;
        } catch (\PDOException $e) {
            throw new DBAlert\QueryError($e->getMessage());
        }
    }
    
    /**
     * Variadic version of $this->single()
     *
     * @param string $statement SQL query without user data
     * @param mixed ...$params Parameters
     * @return mixed
     */
    public function cell(string $statement, ...$params)
    {
        return $this->single($statement, $params);
    }
    
    /**
     * Delete rows in a database table.
     *
     * @param string $table - table name
     * @param array $conditions - WHERE clause
     * @return mixed
     * @throws \TypeError
     */
    public function delete(string $table, array $conditions = [])
    {
        if (empty($conditions)) {
            // Don't allow foot-bullets
            return null;
        }
        $queryString = "DELETE FROM ".$this->escapeIdentifier($table)." WHERE ";
        
        // Simple array for joining the strings together
        $arr = [];
        $params = [];
        foreach ($conditions as $i => $v) {
            $i = $this->escapeIdentifier($i);
            if ($v === null) {
                $arr []= " {$i} IS NULL ";
            } elseif ($v === true) {
                $arr []= " {$i} = TRUE ";
            } elseif ($v === false) {
                $arr [] = " {$i} = FALSE ";
            } elseif (\is_array($v)) {
                throw new \TypeError(
                    \trk('errors.database.array_passed')
                );
            } else {
                $arr []= " {$i} = ? ";
                $params[] = $v;
            }
        }
        $queryString .= \implode(' AND ', $arr);

        return $this->safeQuery($queryString, $params);
    }
    
    /**
     * Make sure only valid characters make it in column/table names
     * 
     * @ref https://stackoverflow.com/questions/10573922/what-does-the-sql-standard-say-about-usage-of-backtick
     * 
     * @param string $string - table or column name
     * @param boolean $quote - certain SQLs escape column names (i.e. mysql with `backticks`)
     * @return string
     * @throws DBAlert\InvalidIdentifier
     */
    public function escapeIdentifier(string $string, bool $quote = true): string
    {
        $str = \preg_replace('/[^0-9a-zA-Z_]/', '', $string);
        
        // The first character cannot be [0-9]:
        if (\preg_match('/^[0-9]/', $str)) {
            throw new DBAlert\InvalidIdentifier(
                \trk('error.database.invalid_identifier', $string)
            );
        }
        
        if ($quote) {
            switch ($this->dbengine) {
                case 'mssql':
                    return '['.$str.']';
                case 'mysql':
                    return '`'.$str.'`';
                default:
                    return '"'.$str.'"';
            }
        }
        return $str;
    }

    /**
     * Create a parenthetical statement e.g. for NOT IN queries.
     *
     * Input: ([1, 2, 3, 5], int)
     * Output: "(1,2,3,5)"
     *
     * @param array $values
     * @param string $type
     * @return string
     * @throws \TypeError
     */
    public function escapeValueSet(array $values, string $type = 'string'): string
    {
        if (empty($values)) {
            // Default value: a subquery that will return an empty set
            return '(SELECT 1 WHERE FALSE)';
        }
        // No arrays of arrays, please
        if (!\is1DArray($values)) {
            throw new \TypeError(
                \trk('errors.database.array_passed')
            );
        }
        // Build our array
        $join = [];
        foreach ($values as $v) {
            switch ($type) {
                case 'int':
                    if (!\is_int($v)) {
                        throw new \TypeError($v . ' is not an integer');
                    }
                    $join[] = (int) $v + 0;
                    break;
                case 'float':
                case 'decimal':
                case 'number':
                case 'numeric':
                    if (!\is_numeric($v)) {
                        throw new \TypeError($v . ' is not a number');
                    }
                    $join[] = (float) $v + 0.0;
                    break;
                case 'string':
                    if (\is_numeric($v)) {
                        $v = (string) $v;
                    }
                    if (!\is_string($v)) {
                        throw new \TypeError($v . ' is not a string');
                    }
                    $join[] = $this->pdo->quote($v, \PDO::PARAM_STR);
                    break;
                default:
                    break 2;
            }
        }
        if (empty($join)) {
            return '(SELECT 1 WHERE FALSE)';
        }
        return '(' . \implode(', ', $join) . ')';
    }

    /**
     * Variadic version of $this->column(), with an offset of 0
     *
     * @param string $statement SQL query without user data
     * @param mixed ...$params Parameters
     * @return mixed
     */
    public function first(string $statement, ...$params)
    {
        return $this->column($statement, $params, 0);
    }
    
    /**
     * Which database driver are we operating on?
     * 
     * @return string
     */
    public function getDriver(): string
    {
        return $this->dbengine;
    }
    
    /**
     * Return the PDO object directly
     * 
     * @return \PDO
     */
    public function getPdo(): \PDO
    {
        return $this->pdo;
    }

    /**
     * Insert a new row to a table in a database.
     *
     * @param string $table - table name
     * @param array $map - associative array of which values should be assigned to each field
     * @return mixed
     * @throws \TypeError
     */
    public function insert(string $table, array $map = [])
    {
        if (empty($map)) {
            return null;
        }

        // Begin query string
        $queryString = "INSERT INTO ".$this->escapeIdentifier($table)." (";

        $placeholder = [];
        $_keys = [];
        $params = [];
        foreach ($map as $k => $v) {
            if ($v !== null) {
                $_keys[] = $k;
                if ($v === true || $v === false) {
                    switch ($this->dbengine) {
                        case 'pgsql':
                        case 'mysql':
                            $placeholder [] = $v ? 'TRUE' : 'FALSE';
                            break;
                    }
                } elseif (\is_array($v)) {
                    throw new \TypeError(
                        \trk('errors.database.array_passed')
                    );
                } else {
                    $placeholder[] = '?';
                    $params[] = $v;
                }
            }
        }

        // Let's make sure our keys are escaped.
        $keys = [];
        foreach ($_keys as $i => $v) {
            $keys[] = $this->escapeIdentifier($v);
        }

        // Now let's append a list of our columns.
        $queryString .= \implode(', ', $keys);

        // This is the middle piece.
        $queryString .= ") VALUES (";

        // Now let's concatenate the ? placeholders
        $queryString .= \implode(', ', $placeholder);

        // Necessary to close the open ( above
        $queryString .= ");";

        // Now let's run a query with the parameters
        return $this->safeQuery($queryString, $params);
    }

    /**
     * Insert a new record then get a particular field from the new row
     *
     * @param string $table
     * @param array $map
     * @param string $field The field name to return of the new entry
     * @return mixed
     *
     * @throws DBAlert\DBException
     * @throws \TypeError
     */
    public function insertGet(string $table, array $map, string $field)
    {
        if ($this->insert($table, $map)) {
            $post = [];
            $params = [];

            foreach ($map as $i => $v) {
                // Escape the identifier to prevent stupidity
                $i = $this->escapeIdentifier($i);
                if ($v === null) {
                    $post []= " {$i} IS NULL ";
                } elseif ($v === true) {
                    $post []= " {$i} = TRUE ";
                } elseif ($v === false) {
                    $post []= " {$i} = FALSE ";
                } elseif (\is_array($v)) {
                    throw new \TypeError(
                        \trk('errors.database.array_passed')
                    );
                } else {
                    // We use prepared statements for handling the users' data
                    $post []= " {$i} = ? ";
                    $params[] = $v;
                }
            }

            $conditions = \implode(' AND ', $post);

            // We want the latest value:
            switch ($this->dbengine) {
                case 'mysql':
                    $limiter = ' ORDER BY '.
                        $this->escapeIdentifier($field).
                        ' DESC LIMIT 0, 1 ';
                    break;
                case 'pgsql':
                    $limiter = ' ORDER BY '.
                        $this->escapeIdentifier($field).
                        ' DESC OFFSET 0 LIMIT 1 ';
                    break;
                default:
                    $limiter = '';
            }

            $query = 'SELECT '.
                $this->escapeIdentifier($field).
                ' FROM '.
                $this->escapeIdentifier($table).
                ' WHERE ' . $conditions . $limiter;

            return $this->single($query, $params);
        } else {
            throw new DBAlert\DBException(
                \trk('errors.database.insert_failed', $table, $this->pdo->errorInfo()[2])
            );
        }
    }
    
    /**
     * Insert many new rows to a table in a database. using the same prepared statement
     *
     * @param string $table - table name
     * @param array $maps - array of associative array specifying values should be assigned to each field
     * @return bool
     * @throws DBAlert\QueryError
     */
    public function insertMany(string $table, array $maps): bool
    {
        if (empty($maps)) {
            return null;
        }
        $first = $maps[0];
        foreach ($maps as $map) {
            if (\count($map) < 1 || \count($map) !== \count($first)) {
                throw new \InvalidArgumentException(
                    'Every array in the second argument should have the same number of columns'
                );
            }
        }

        // Begin query string
        $queryString = "INSERT INTO ".$this->escapeIdentifier($table)." (";

        // Let's make sure our keys are escaped.
        $keys = \array_keys($first);
        foreach ($keys as $i => $v) {
            $keys[$i] = $this->escapeIdentifier($v);
        }

        // Now let's append a list of our columns.
        $queryString .= \implode(', ', $keys);

        // This is the middle piece.
        $queryString .= ") VALUES (";

        // Now let's concatenate the ? placeholders
        $queryString .= \implode(
            ', ', 
            \array_fill(0, \count($first), '?')
        );

        // Necessary to close the open ( above
        $queryString .= ");";

        // Now let's run a query with the parameters
        $stmt = $this->pdo->prepare($queryString);
        foreach ($maps as $params) {
            if ($stmt->execute($params) === false) {
                throw new DBAlert\QueryError($queryString, $params);
            }
        }
        return true;
    }

    /**
     * Similar to $this->row() except it only returns a single row
     *
     * @param string $statement SQL query without user data
     * @param mixed ...$params Parameters
     * @return mixed
     * @throws DBAlert\QueryError
     */
    public function row(string $statement, ...$params)
    {
        try {
            if (empty($params)) {
                $stmt = $this->pdo->query($statement);
                if ($stmt !== false) {
                    return $stmt->fetch(\PDO::FETCH_ASSOC);
                }
                return false;
            }
            $stmt = $this->pdo->prepare($statement);
            if ($stmt->execute($params) === false) {
                throw new DBAlert\QueryError(
                    $this->errorInfo()[2] ?? 'An unknown error has occurred',
                    $this->errorInfo()[1] ?? 0
                );
            }
            return $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            throw new DBAlert\QueryError(
                $e->getMessage(),
                (int) $e->getCode()
            );
        }
    }

    /**
     * PHP 5.6 variadic shorthand for $this->safeQuery()
     *
     * @param string $statement SQL query without user data
     * @param mixed[] ...$params Parameters
     * @return mixed - If successful, a 2D array
     */
    public function run(string $statement, ...$params)
    {
        return $this->safeQuery($statement, $params);
    }

    /**
     * Perform a Parameterized Query
     *
     * @param string $statement
     * @param array $params
     * @param int $fetch_style
     * @return mixed -- array if SELECT
     *
     * @throws DBAlert\QueryError
     */
    public function safeQuery(
        string $statement,
        array $params = [],
        int $fetch_style = \PDO::FETCH_ASSOC
    ) {
        try {
            if (empty($params)) {
                $stmt = $this->pdo->query($statement);
                if ($stmt !== false) {
                    return $stmt->fetchAll($fetch_style);
                }
                return false;
            }
            $stmt = $this->pdo->prepare($statement);
            if ($stmt === false) {
                throw new DBAlert\QueryError(
                    $this->errorInfo()[2] ?? \json_encode([$statement, $params]),
                    (int) $this->errorInfo()[1]
                );
            }
            if ($stmt->execute($params) === false) {
                throw new DBAlert\QueryError(
                    $this->errorInfo()[2] ?? \json_encode([$statement, $params]),
                    (int) $this->errorInfo()[1]
                );
            }
            return $stmt->fetchAll($fetch_style);
        } catch (\PDOException $e) {
            throw new DBAlert\QueryError(
                $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Fetch a single result -- useful for SELECT COUNT() queries
     *
     * @param string $statement
     * @param array $params
     * @return mixed
     * @throws DBAlert\QueryError
     * @throws \TypeError
     */
    public function single(string $statement, array $params = [])
    {
        if (!\is1DArray($params)) {
            throw new \TypeError(
                \trk('errors.database.array_passed')
            );
        }
        $stmt = $this->pdo->prepare($statement);
        if ($stmt === false) {
            throw new DBAlert\QueryError(
                $this->errorInfo()[2],
                $this->errorInfo()[1]
            );
        }
        $exec = $stmt->execute($params);
        if ($exec === false) {
            throw new DBAlert\QueryError(
                $this->errorInfo()[2],
                $this->errorInfo()[1]
            );
        }
        return $stmt->fetchColumn(0);
    }

    /**
     * Update a row in a database table.
     *
     * @param string $table - table name
     * @param array $changes - associative array of which values should be assigned to each field
     * @param array $conditions - WHERE clause
     * @return mixed
     * @throws \TypeError
     */
    public function update(string $table, array $changes, array $conditions)
    {
        if (empty($changes) || empty($conditions)) {
            return null;
        }
        $params = [];
        $queryString = "UPDATE ".$this->escapeIdentifier($table)." SET ";
        
        // The first set (pre WHERE)
        $pre = [];
        foreach ($changes as $i => $v) {
            $i = $this->escapeIdentifier($i);
            if ($v === null) {
                $pre [] = " {$i} = NULL";
            } elseif ($v === true) {
                $pre []= " {$i} = TRUE ";
            } elseif ($v === false) {
                $pre []= " {$i} = FALSE ";
            } elseif (\is_array($v)) {
                throw new \TypeError(
                    \trk('errors.database.array_passed')
                );
            } else {
                $pre [] = " {$i} = ?";
                $params[] = $v;
            }
        }
        $queryString .= \implode(', ', $pre);
        $queryString .= " WHERE ";
        
        // The last set (post WHERE)
        $post = [];
        foreach ($conditions as $i => $v) {
            $i = $this->escapeIdentifier($i);
            if ($v === null) {
                $post []= " {$i} IS NULL ";
            } elseif ($v === true) {
                $post []= " {$i} = TRUE ";
            } elseif ($v === false) {
                $post []= " {$i} = FALSE ";
            } elseif (\is_array($v)) {
                throw new \TypeError(
                    \trk('errors.database.array_passed')
                );
            } else {
                $post [] = " {$i} = ? ";
                $params[] = $v;
            }
        }
        $queryString .= \implode(' AND ', $post);

        return $this->safeQuery($queryString, $params);
    }
    
    /**
     ***************************************************************************
     ***************************************************************************
     ****             PUNTER METHODS - see PDO class definition             ****
     ***************************************************************************
     ***************************************************************************
    **/
    
    /**
     * Initiates a transaction
     *
     * @return bool
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }
    /**
     * Commits a transaction
     *
     * @return bool
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }
    /**
     * Fetch the SQLSTATE associated with the last operation on the database
     * handle
     *
     * @return string
     */
    public function errorCode(): string
    {
        return $this->pdo->errorCode();
    }
    /**
     * Fetch extended error information associated with the last operation on 
     * the database handle
     *
     * @return array
     */
    public function errorInfo(): array
    {
        return $this->pdo->errorInfo();
    }

    /**
     * Execute an SQL statement and return the number of affected rows
     *
     * @param string $statement
     * @return int
     */
    public function exec(string $statement): int
    {
        return $this->pdo->exec($statement);
    }

    /**
     * Retrieve a database connection attribute
     *
     * @param int $attr
     * @return mixed
     */
    public function getAttribute(int $attr)
    {
        return $this->pdo->getAttribute($attr);
    }

    /**
     * Return an array of available PDO drivers
     *
     * @return array
     */
    public function getAvailableDrivers(): array
    {
        return $this->pdo->getAvailableDrivers();
    }

    /**
     * Checks if inside a transaction
     *
     * @return bool
     */
    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    /**
     * Returns the ID of the last inserted row or sequence value
     * @param mixed ...$args
     * @return string
     */
    public function lastInsertId(...$args): string
    {
        return $this->pdo->lastInsertId(...$args);
    }

    /**
     * Prepares a statement for execution and returns a statement object
     * @param mixed ...$args
     * @return \PDOStatement|bool
     */
    public function prepare(...$args)
    {
        return $this->pdo->prepare(...$args);
    }

    /**
     * Executes an SQL statement, returning a result set as a PDOStatement object
     * @param string $statement
     * @param int $mode
     * @param mixed $arg3
     * @return \PDOStatement|bool
     */
    public function query(
        string $statement,
        int $mode = \PDO::ATTR_DEFAULT_FETCH_MODE,
        $arg3 = null
    ) {
        if ($arg3) {
            return $this->pdo->query($statement, $mode, $arg3);
        }
        return $this->pdo->query($statement, $mode);
    }

    /**
     * Quotes a string for use in a query
     *
     * @param mixed ...$args
     * @return string
     */
    public function quote(...$args): string
    {
        return $this->pdo->quote(...$args);
    }

    /**
     * Rolls back a transaction
     *
     * @return bool
     */
    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    /**
     * Set an attribute
     *
     * @param mixed ...$args
     * @return bool
     */
    public function setAttribute(...$args): bool
    {
        return $this->pdo->setAttribute(...$args);
    }
}
