<?php
declare(strict_types=1);
namespace Airship\Engine\Bolt;

use \Airship\Engine\State;
use \Psr\Log\LogLevel;

trait Log
{
    /**
     * Log a message with a specific error level
     * 
     * @param string $message
     * @param string $level
     * @param array $context
     * @return mixed
     */
    public function log(
        string $message, 
        string $level = LogLevel::ERROR,
        array $context = []
    ) {
        $state = State::instance();
        return $state->logger->log(
            $level,
            $message,
            $context
        );
    }
}
