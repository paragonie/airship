<?php
declare(strict_types=1);
namespace Airship\Engine;

use \Airship\Engine\Contract\LedgerStorageInterface;

class Ledger implements \Psr\Log\LoggerInterface
{
    protected $storage;
    
    public function __construct(LedgerStorageInterface $storage = null, ...$args)
    {
        $this->storage = $storage;
    }

    /**
     * All things equal, this information should be included.
     *
     * @return array
     */
    public function defaultContext()
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
     * @return type
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
     * 
     * @param type $message
     * @param array $context
     * @return type
     */
    public function emergency($message, array $context = [])
    {
        return $this->log(\Psr\Log\LogLevel::EMERGENCY, $message, $context);
    }
    
    public function critical($message, array $context = [])
    {
        return $this->log(\Psr\Log\LogLevel::CRITICAL, $message, $context);
    }
    
    public function alert($message, array $context = [])
    {
        return $this->log(\Psr\Log\LogLevel::ALERT, $message, $context);
    }
    
    public function error($message, array $context = [])
    {
        return $this->log(\Psr\Log\LogLevel::ERROR, $message, $context);
    }
    
    public function warning($message, array $context = [])
    {
        return $this->log(\Psr\Log\LogLevel::WARNING, $message, $context);
    }
    
    public function notice($message, array $context = [])
    {
        return $this->log(\Psr\Log\LogLevel::NOTICE, $message, $context);
    }
    
    public function info($message, array $context = [])
    {
        return $this->log(\Psr\Log\LogLevel::INFO, $message, $context);
    }
    
    public function debug($message, array $context = [])
    {
        return $this->log(\Psr\Log\LogLevel::DEBUG, $message, $context);
    }
}