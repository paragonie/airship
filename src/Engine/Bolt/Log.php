<?php
declare(strict_types=1);
namespace Airship\Engine\Bolt;

use \Airship\Engine\State;
use \Psr\Log\LogLevel;

/**
 * Trait Log
 *
 * Adds a common log() method.
 *
 * @package Airship\Engine\Bolt
 */
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
        if ($level === LogLevel::DEBUG) {
            $state = State::instance();
            if (!$state->universal['debug']) {
                // Don't log debug messages unless debug mode is on:
                return null;
            }
        }
        $state = State::instance();
        return $state->logger->log(
            $level,
            $message,
            $context
        );
    }
}
