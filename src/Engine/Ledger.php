<?php
declare(strict_types=1);
namespace Airship\Engine;

use \Airship\Engine\Contract\LedgerStorageInterface;
use \Psr\Log\LoggerInterface;
use \Psr\Log\LogLevel;

/**
 * Class Ledger
 * @package Airship\Engine
 */
class Ledger implements LoggerInterface
{
    protected $storage;

    /**
     * Ledger constructor.
     * @param LedgerStorageInterface|null $storage
     * @param mixed[] ...$args
     */
    public function __construct(LedgerStorageInterface $storage = null, ...$args)
    {
        $this->storage = $storage;

        // We don't use $args, but a Gear can.
    }

    /**
     * All things equal, this information should be included.
     *
     * @return array
     */
    public function defaultContext(): array
    {
        if (ISCLI) {
            return [
                'ip' => 'localhost',
                'hostname' => 'localhost',
                'port' => null,
                'user_agent' => 'cli',
                'referrer' => null,
                'uri' => $GLOBALS['argv'][0] ?? ''
            ];
        }
        return [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'hostname' => $_SERVER['HTTP_HOST'] ?? null,
            'port' => $_SERVER['SERVER_PORT'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'referrer' => $_SERVER['HTTP_REFERER'] ?? null,
            'uri' => $_SERVER['REQUEST_URI'] ?? ''
        ];
    }
    
    /**
     * Log a message, optionally sign and seal it, if you passed the right keys
     * to the constructor.
     * 
     * @param string $level
     * @param string $message
     * @param array $context
     * @return mixed
     */
    public function log($level, $message, array $context = [])
    {
        $context = $this->defaultContext() + $context;
        return $this->storage->store(
            $level,
            $message,
            \json_encode($context)
        );
    }

    /**
     * Store an EMERGENCY message
     *
     * @param string $message
     * @param array $context
     * @return mixed
     */
    public function emergency($message, array $context = [])
    {
        return $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * Store a CRITICAL message
     *
     * @param string $message
     * @param array $context
     * @return mixed
     */
    public function critical($message, array $context = [])
    {
        return $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Store an ALERT message
     *
     * @param string $message
     * @param array $context
     * @return mixed
     */
    public function alert($message, array $context = [])
    {
        return $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * Store an ERROR message
     *
     * @param string $message
     * @param array $context
     * @return mixed
     */
    public function error($message, array $context = [])
    {
        return $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Store a WARNING message
     *
     * @param string $message
     * @param array $context
     * @return mixed
     */
    public function warning($message, array $context = [])
    {
        return $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Store a NOTICE message
     *
     * @param string $message
     * @param array $context
     * @return mixed
     */
    public function notice($message, array $context = [])
    {
        return $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Store a INFO message
     *
     * @param string $message
     * @param array $context
     * @return mixed
     */
    public function info($message, array $context = [])
    {
        return $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * Store a DEBUG message
     *
     * @param string $message
     * @param array $context
     * @return mixed
     */
    public function debug($message, array $context = [])
    {
        return $this->log(LogLevel::DEBUG, $message, $context);
    }
}