<?php
declare(strict_types=1);
namespace Airship\Engine\LedgerStorage;

use Airship\Engine\Contract\{
    DBInterface,
    LedgerStorageInterface
};

/**
 * Class DBStore
 * @package Airship\Engine\LedgerStorage
 */
class DBStore implements LedgerStorageInterface
{
    const DEFAULT_TABLE = 'airship_logs';

    /**
     * @var string
     */
    protected $table;

    /**
     * @var DBInterface
     */
    protected $db;

    /**
     * @var string[]
     */
    protected $columns = [
        'level' => 'level',
        'message' => 'message',
        'context' => 'context'
    ];

    /**
     * DBStore constructor.
     * @param DBInterface|null $db
     * @param string $table
     */
    public function __construct(
        DBInterface $db = null,
        string $table = self::DEFAULT_TABLE
    ) {
        $this->db = $db ?? \Airship\get_database();
        if (empty($table)) {
            $table = self::DEFAULT_TABLE;
        }
        $this->table = $table;
    }

    /**
     * Store a log message -- used by Ledger
     * 
     * @param string $level
     * @param string $message
     * @param string $context (JSON encoded)
     * @return mixed
     */
    public function store(
        string $level,
        string $message,
        string $context
    ) {
        return $this->db->insert(
            $this->table,
            [
                $this->columns['level'] => $level,
                $this->columns['message'] => $message,
                $this->columns['context'] => $context
            ]
        );
    }

    /**
     * Change a column name.
     *
     * @param string $key
     * @param string $value
     * @return self
     */
    public function setColumn(
        string $key,
        string $value
    ): self {
        $this->columns[$key] = $value;
        return $this;
    }
}
