<?php
declare(strict_types=1);
namespace Airship\Engine;

use \Airship\Engine\Bolt\{
    Log as LogBolt,
    Security as SecurityBolt
};

/**
 * For MVC developers, this is analogous to a Model
 */
class Blueprint
{
    use LogBolt;
    use SecurityBolt;

    public $db;
    
    public function __construct(Database $db = null)
    {
        $this->db = $db;
    }
    
    /**
     * Shorthand for $this->db->escapeIdentifier()
     * 
     * @param string $identifier
     * @return string
     */
    public function e(string $identifier): string
    {
        return $this->db->escapeIdentifier($identifier);
    }
}
