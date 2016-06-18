<?php
declare(strict_types=1);
namespace Airship\Engine\Security;

use \Airship\Engine\{
    Database,
    State
};

/**
 * Class AirBrake
 *
 * Progressive rate-limiting
 *
 * @package Airship\Engine\Security
 */
class AirBrake
{
    /**
     * @var \Airship\Engine\Database
     */
    protected $db;

    public function __construct(Database $db = null)
    {
        if (!$db) {
            $db = \Airship\get_database();
        }
        $this->db = $db;
    }
}
