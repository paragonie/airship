<?php
declare(strict_types=1);
namespace Airship\Engine;

/**
 * Class State
 * @package Airship\Engine
 *
 * Registry Singleton for keeping track of application state
 *
 * The main purpose of this is to enable the Gears system to function, which lets us dynamically
 * swap out classes for upgraded versions at runtime.
 */
class State implements \IteratorAggregate, \ArrayAccess, \Serializable, \Countable
{
    /**
     * @var ?State
     */
    private static $instance = null;

    /**
     * @var \ArrayObject
     */
    private $engine_state_registry;

    /**
     * How many things are in the registry?
     * 
     * @return int
     */
    public function count(): int
    {
        return \count($this->engine_state_registry);
    }
    
    /**
     * Get something
     * 
     * @param string $key
     * @return mixed
     */
    public function get($key = null)
    {
        return $this->__get($key);
    }
    
    /**
     * @return \Iterator
     */
    public function getIterator(): \Iterator
    {
        return $this->engine_state_registry->getIterator();
    }
    
    /**
     * Does it exist?
     * 
     * @param string $key
     * @return boolean
     */
    public function offsetExists($key)
    {
        return isset($this->engine_state_registry[$key]);
    }
    
    /**
     * Get something
     * 
     * @param string $key
     * @return mixed
     */
    public function offsetGet($key)
    {
        return $this->offsetExists($key)
            ? $this->engine_state_registry[$key]
            : null;
    }

    /**
     * Store an object in the registry
     * 
     * @param ?string $key
     * @param mixed $value
     * @return void
     */
    public function offsetSet($key, $value)
    {
        if (\is_null($key)) {
            $this->engine_state_registry[] = $value;
        } else {
            $this->engine_state_registry[$key] = $value;
        }
    }
    
    /**
     * Delete an entry from the registry
     * 
     * @param mixed $key
     * @return void
     */
    public function offsetUnset($key)
    {
        if ($this->offsetExists($key)) {
            unset($this->engine_state_registry[$key]);
        }
    }
    
    /**
     * Return a JSON encoded representation of the registry
     * 
     * @return string
     * @throws \Error
     */
    public function serialize()
    {
        /** @var string $string */
        $string = \json_encode(
            $this->engine_state_registry,
            JSON_PRETTY_PRINT
        );
        if (!\is_string($string)) {
            throw new \Error('Could not sanitize string');
        }
        return $string;
    }

    /**
     * Proxy method
     *
     * @param mixed $key
     * @param mixed $value
     * @return void
     */
    public function set($key = null, $value = null)
    {
        $this->__set($key, $value);
    }
    
    /**
     * 
     * @param string $serialized
     * @return array
     * @throws \Error
     * @psalm-suppress ImplementedReturnTypeMismatch
     */
    public function unserialize($serialized)
    {
        $array = \json_decode($serialized, true);
        if (!\is_array($array)) {
            throw new \Error('Invalid JSON message');
        }
        return $array;
    }
    
    /** BEGIN SINGLETON REQUIREMENTS **/
    
    /**
     * Return the sole instance of this singleton
     * 
     * @return \Airship\Engine\State
     */
    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new State();
        }

        return self::$instance;
    }

    /**
     * Create an array object
     */
    private function __construct()
    {
        $this->engine_state_registry = new \ArrayObject();
    }

    /**
     * NOP - no cloning allowed
     */
    private function __clone()
    {
        // You shall not pass!
        
        throw new \Error(
            \__('You cannot clone a singleton (i.e. \Airship\Engine\State).')
        );
    }
    
    /** END SINGLETON REQUIREMENTS **/
    
    /**
     * Grab some data
     * 
     * @param string $key
     * @return mixed
     */
    public function __get($key = null)
    {
        if (empty($key)) {
            return null;
        }
        if (isset($this->engine_state_registry[$key])) {
            return $this->engine_state_registry[$key];
        }
        return null;
    }
    
    /**
     * Store something in the state
     * 
     * @param string $key
     * @param mixed $value
     */
    public function __set($key, $value)
    {
        $this->engine_state_registry[$key] = $value;
    }

    /**
     * Does this exist?
     * 
     * @param mixed $key
     * @return boolean
     */
    public function __isset($key): bool
    {
        return isset($this->engine_state_registry[$key]);
    }

    /**
     * This runs when a variable is unset
     * 
     * @param mixed $key
     */
    public function __unset($key)
    {
        if (isset($this->engine_state_registry[$key])) {
            unset($this->engine_state_registry[$key]);
        }
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->serialize();
    }
}

