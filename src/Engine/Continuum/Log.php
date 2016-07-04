<?php
declare(strict_types=1);
namespace Airship\Engine\Continuum;

use \Airship\Engine\Database;

/**
 * Class Log
 * @package Airship\Engine\Continuum
 */
class Log
{
    /**
     * @var string
     */
    protected $component;

    /**
     * ContinuumLog constructor.
     * @param Database|null $db
     * @param string $component
     */
    public function __construct(Database $db = null, string $component = 'continuum')
    {
        if (!$db) {
            $db = \Airship\get_database();
        }
        $this->db = $db;
        $this->component = $component;
    }

    /**
     * Store information inside of the Continuum Log
     *
     * @param string $level
     * @param string $message
     * @param array $context
     * @return mixed
     */
    public function store(string $level, string $message, array $context = [])
    {
        return $this->db->insertGet(
            'airship_continuum_log',
            [
                'loglevel' => $level,
                'component' => $this->component,
                'message' => $message,
                'context' => \json_encode($context)
            ],
            'logid'
        );
    }
}
