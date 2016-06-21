<?php
declare(strict_types=1);
namespace Airship\Engine\Contract;

/**
 * Interface DBInterface
 *
 * An interface for database interaction.
 *
 * @package Airship\Engine\Contract
 */
interface DBInterface
{
    /**
     * Variadic version of $this->column()
     *
     * @param string $statement SQL query without user data
     * @param int $offset - How many columns from the left are we grabbing from each row?
     * @param mixed ...$params Parameters
     * @return mixed
     */
    public function col(string $statement, int $offset = 0, ...$params);
    
    /**
     * Fetch a column
     *
     * @param string $statement SQL query without user data
     * @param array $params Parameters
     * @param int $offset - How many columns from the left are we grabbing from each row?
     * @return mixed
     */
    public function column(string $statement, array $params = [], int $offset = 0);
    
    /**
     * Variadic version of $this->single()
     *
     * @param string $statement SQL query without user data
     * @param mixed ...$params Parameters
     * @return mixed
     */
    public function cell(string $statement, ...$params);
    
    /**
     * Delete rows in a database table.
     *
     * @param string $table - table name
     * @param array $conditions - WHERE clause
     */
    public function delete(string $table, array $conditions);

    /**
     * Variadic version of $this->column(), with an offset of 0
     *
     * @param string $statement SQL query without user data
     * @param mixed ...$params Parameters
     * @return mixed
     */
    public function first(string $statement, ...$params);

    /**
     * Which database driver are we operating on?
     *
     * @return string
     */
    public function getDriver(): string;

    /**
     * Return the PDO object directly
     *
     * @return \PDO
     */
    public function getPdo(): \PDO;
    
    /**
     * Insert a new row to a table in a database.
     *
     * @param string $table - table name
     * @param array $map - associative array of which values should be assigned to each field
     */
    public function insert(string $table, array $map);
    
    /**
     * Similar to $this->run() except it only returns a single row
     *
     * @param string $statement SQL query without user data
     * @param mixed ...$params Parameters
     */
    public function row(string $statement, ...$params);
    
    /**
     * Run a query, get a 2D array with all the results
     *
     * @param string $statement SQL query without user data
     * @param mixed ...$params Parameters
     * @return mixed - If successful, a 2D array
     */
    public function run(string $statement, ...$params);
    
    /**
     * Fetch a single result -- useful for SELECT COUNT() queries
     *
     * @param string $statement
     * @param array $params
     * @return mixed
     */
    public function single(string $statement, array $params = []);
    
    /**
     * Update a row in a database table.
     *
     * @param string $table - table name
     * @param array $changes - associative array of which values should be assigned to each field
     * @param array $conditions - WHERE clause
     */
    public function update(string $table, array $changes, array $conditions);
}
