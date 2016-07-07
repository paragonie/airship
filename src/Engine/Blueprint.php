<?php
declare(strict_types=1);
namespace Airship\Engine;

use Airship\Engine\Bolt\{
    Log as LogBolt,
    Security as SecurityBolt
};

/**
 * Class Blueprint
 *
 * For MVC developers, this is analogous to a Model
 * 
 * @package Airship\Engine
 */
class Blueprint
{
    use LogBolt;
    use SecurityBolt;

    /**
     * @var Database
     */
    public $db;

    /**
     * Blueprint constructor.
     * @param Database|null $db
     */
    public function __construct(Database $db = null)
    {
        if (!$db) {
            $db = \Airship\get_database();
        }
        $this->db = $db;
    }
    
    /**
     * Shorthand for $this->db->escapeIdentifier()
     *
     * Feel free to use for table/column names, but DO NOT use this for values!
     * 
     * @param string $identifier
     * @return string
     */
    public function e(string $identifier): string
    {
        return $this->db->escapeIdentifier($identifier);
    }
}
