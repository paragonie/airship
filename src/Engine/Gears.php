<?php
declare(strict_types=1);
namespace Airship\Engine;

use Airship\Alerts\{
    GearNotFound,
    GearWrongType
};
use ParagonIE\ConstantTime\Base64UrlSafe;
use ParagonIE\Halite\Util as CryptoUtil;

/**
 * Class Gears
 *
 * The gears class contains a bunch of methods for the plugin system
 * (Airship Gears)
 *
 * @package Airship\Engine
 */
abstract class Gears
{
    /**
     * Attach a plugin to overload the base class
     * 
     * @param string $index         Index
     * @param string $new_type      New type
     * @param string $namespace     Namespace
     * @param bool   $replaceParent We're replacing the parent class entirely.
     * @return bool
     * @throws GearNotFound
     * @throws GearWrongType
     */
    public static function attach(
        string $index,
        string $new_type,
        string $namespace = '',
        bool   $replaceParent = false
    ): bool {
        $state = State::instance();
        $gears = $state->gears;
        $contracts = $state->gearContracts;
        
        $type = empty($namespace)
            ? $new_type
            : $namespace . '\\' . $new_type;
        
        if (!\class_exists($type)) {
            throw new GearNotFound($new_type);
        }
        if (!isset($gears[$index])) {
            throw new GearNotFound($index);
        }
        
        $reflector = new \ReflectionClass($type);
        if (!$replaceParent) {
            if (!$reflector->isSubclassOf($gears[$index])) {
                throw new GearWrongType(
                    \__(
                        '%s does not inherit from %s', 'default',
                        $type,
                        $gears[$index]
                    )
                );
            }
        }
        if (!empty($contracts[$index])) {
            $reflector = new \ReflectionClass($type);
            if (!$reflector->implementsInterface($contracts[$index])) {
                throw new GearWrongType(
                    \__(
                        '%s does not implement %s', 'default',
                        $type,
                        $gears[$index]
                    )
                );
            }
        }
        $gears[$index] = $type;
        $state->gears = $gears;
        return true;
    }
    
    /**
     * Execute a block of code.
     * 
     * @param string $code
     * @param boolean $cache
     * @param boolean $do_not_eval
     * @return mixed
     */
    protected static function coreEval(
        string $code,
        bool $cache = false,
        bool $do_not_eval = false
    ) {
        \clearstatcache();
        if ($do_not_eval || \Airship\is_disabled('eval')) {
            if ($cache) {
                if (!\file_exists(ROOT."/tmp/cache/gear")) {
                    \mkdir(ROOT."/tmp/cache/gear", 0777);
                    \clearstatcache();
                }
                $hashed = Base64UrlSafe::encode(
                    CryptoUtil::raw_hash($code, 33)
                );
                if (!\file_exists(ROOT . '/tmp/cache/gear/' . $hashed . '.tmp.php')) {
                    \file_put_contents(
                        ROOT.'/tmp/cache/gear/'.$hashed.'.tmp.php',
                        '<?php'."\n".$code
                    );
                }
                return self::sandboxRequire(ROOT . '/cache/' . $hashed . '.tmp.php');
            } else {
                if (!\file_exists(ROOT . '/tmp/gear')) {
                    \mkdir(ROOT . '/tmp/gear', 0777);
                    \clearstatcache();
                }
                $file = \Airship\tempnam('gear-', 'php', ROOT.'/tmp/gear');
                \file_put_contents(
                    $file,
                    '<?php' . "\n" . $code
                );
                \clearstatcache();
                $ret = self::sandboxRequire($file);
                \unlink($file);
                \clearstatcache();
                return $ret;
            }
        } else {
            return eval($code);
        }
    }

    /**
     * Require all new gears adhere to the contract given within
     *
     * @param string $index
     * @param string $type
     * @return bool
     */
    public static function bind(string $index, string $type): bool
    {
        $state = State::instance();
        if (!isset($state->gearContracts[$index])) {
            $contracts = $state->gearContracts;
            if (\interface_exists($type)) {
                $contracts[$index] = $type;
                $state->gearContracts = $contracts;
                return true;
            }
        }
        return false;
    }
    
    /**
     * Create a shim that extends a gear type
     * 
     * @param string $index
     * @param string $desired_name
     * @param string $namespace Namespace
     * @return string
     * @throws GearNotFound
     */
    public static function extract(
        string $index,
        string $desired_name, 
        string $namespace = ''
    ): string {
        $state = State::instance();
        if (!isset($state->gears[$index])) {
            throw new GearNotFound($index);
        }
        $latest = $state->gears[$index];
        
        $class_name = $desired_name;
        $testName = empty($namespace)
            ? $class_name
            : $namespace . '\\' . $class_name;
        
        $i = 0;
        while (\class_exists($testName, false)) {
            $class_name = $desired_name . '_' . \bin2hex('' . ++$i);
            $testName = empty($namespace)
                ? $class_name
                : $namespace . '\\' . $class_name;
        }
        
        // Create the shim
        if (!empty($namespace)) {
            self::coreEval(
                'namespace '.$namespace.';'.
                "\n\n".
                'class '.$class_name.' extends '.$latest.' { }'
            );
        } else {
            self::coreEval(
                'class '.$class_name.' extends '.$latest.' { }'
            );
        }
        return $class_name;
    }
    
    /**
     * Add a new type to the Gears registry
     * 
     * @param string $index
     * @param string $type
     * @return bool
     */
    public static function forge(string $index, string $type): bool
    {
        $state = State::instance();
        if (!isset($state->gears[$index])) {
            $gears = $state->gears;
            if (\class_exists($type)) {
                $gears[$index] = $type;
                $state->gears = $gears;
                return true;
            }
        }
        return false;
    }
    
    /**
     * Get an instance of a gear (all arguments after the first are passed to 
     * its constructor)
     * 
     * @param string $name - Gear identifier
     * @param array<int, mixed> $args - constructor parameters
     * @return mixed
     */
    public static function get(string $name, ...$args)
    {
        $className = self::getName($name);
        $obj = new $className(...$args);
        return $obj;
    }

    /**
     * Get the class name of a Gear
     * 
     * @param string $name
     * @return string
     * @throws GearNotFound
     */
    public static function getName(string $name)
    {
        $state = State::instance();
        if (!isset($state->gears[$name])) {
            throw new GearNotFound($name);
        }
        $gears = $state->gears;
        return $gears[$name];
    }

    /**
     * Set up initial classes
     * 
     * @param array $gears
     * @return void
     * @throws \TypeError
     */
    public static function init(array $gears = []): void
    {
        foreach ($gears as $index => $type) {
            if (\is_array($type)) {
                if (\count($type) !== 2) {
                    throw new \TypeError('Must be an array of two elements.');
                }
                $type = \array_values($type);
                self::forge($index, (string) $type[0]);
                self::bind($index, (string) $type[1]);
            } else {
                self::forge($index, $type);
            }
        }
    }

    /**
     * Add a new type to the Gears registry
     *
     * @param string $index
     * @param string $type
     * @param string|null $bind
     * @return bool
     */
    public static function lazyForge(string $index, string $type, ?string $bind = null): bool
    {
        $state = State::instance();
        if (!isset($state->gears[$index])) {
            $gears = $state->gears;
            $gears[$index] = $type;
            $state->gears = $gears;
            if ($bind !== null) {
                $contracts            = $state->gearContracts;
                $contracts[$index]    = $bind;
                $state->gearContracts = $contracts;
            }
            return true;
        }
        return false;
    }
    
    /**
     * Load a file in a way that doesn't allow access to the parent method's
     * internal functions
     * 
     * @param string $file
     * @return bool
     * @psalm-suppress UnresolvableInclude
     */
    protected static function sandboxRequire(string $file): bool
    {
        return (require $file) === 1;
    }
}
