<?php
declare(strict_types=1);
namespace Airship\Engine;

use \Airship\Alerts\{
    GearNotFound,
    GearWrongType
};
use \ParagonIE\ConstantTime\Base64UrlSafe;

/**
 * The gears class contains a bunch of methods for the plugin system 
 * (Airship Gears)
 */
abstract class Gears
{
    /**
     * Attach a plugin to overload the base class
     * 
     * @param string $index    Index
     * @param string $new_type New type
     * @param string $ns       Namespace
     * @return bool
     * @throws GearNotFound
     * @throws GearWrongType
     */
    public static function attach(string $index, string $new_type, string $ns = '')
    {
        $state = State::instance();
        $gears = $state->gears;
        
        $type = empty($ns) ? $new_type : $ns.'\\'.$new_type;
        
        if (!\class_exists($type)) {
            throw new GearNotFound($new_type);
        }
        if (!isset($gears[$index])) {
            throw new GearNotFound($index);
        }
        
        $reflector = new \ReflectionClass($type);
        if (!$reflector->isSubclassOf($gears[$index])) {
            throw new GearWrongType("{$type} does not inherit from {$gears[$index]}");
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
     * @param boolean $dont_eval
     * @return mixed
     */
    protected static function coreEval(
        string $code,
        bool $cache = false,
        bool $dont_eval = false
    ) {
        \clearstatcache();
        if ($dont_eval || \Airship\is_disabled('eval')) {
            if ($cache) {
                if (!\file_exists(ROOT."/tmp/cache/gear")) {
                    \mkdir(ROOT."/tmp/cache/gear", 0777);
                    \clearstatcache();
                }
                $hashed =Base64UrlSafe::encode(
                    \Sodium\crypto_generichash($code, null, 33)
                );
                if (!\file_exists(ROOT.'/tmp/cache/gear/'.$hashed.'.tmp.php')) {
                    \file_put_contents(
                        ROOT.'/tmp/cache/gear/'.$hashed.'.tmp.php',
                        '<?php'."\n".$code
                    );
                }
                return self::sandboxRequire(ROOT.'/cache/'.$hashed.'.tmp.php');
            } else {
                if (!\file_exists(ROOT.'/tmp/gear')) {
                    \mkdir(ROOT.'/tmp/gear', 0777);
                    \clearstatcache();
                }
                $file = \tempnam(ROOT.'/tmp/gear');
                \file_put_contents(
                    $file,
                    '<?php'."\n".$code
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
     * Create a shim that extends a gear type
     * 
     * @param string $index
     * @param string $desired_name
     * @throws \Airship\Alerts\GearNotFound
     * @return string
     */
    public static function extract(
        string $index,
        string $desired_name, 
        string $ns = ''
    ) {
        $state = \Airship\Engine\State::instance();
        if (!isset($state->gears[$index])) {
            throw new \Airship\Alerts\GearNotFound();
        }
        $latest = $state->gears[$index];
        
        $class_name = $desired_name;
        $testName = empty($ns) ? $class_name : $ns.'\\'.$class_name;
        
        $i = 0;
        while (\class_exists($testName, false)) {
            $class_name = $desired_name . '_' . \bin2hex('' . ++$i);
            $testName = empty($ns) ? $class_name : $ns.'\\'.$class_name;
        }
        
        // Create the shim
        if (!empty($ns)) {
            self::coreEval(
                'namespace '.$ns.';'.
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
     */
    public static function forge(string $index, string $type)
    {
        $state = \Airship\Engine\State::instance();
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
     * @params $args - constructor parameters
     * @return object
     */
    public static function get(string $name, ...$args)
    {
        $classname = self::getName($name);
        $obj = new $classname(...$args);
        return $obj;
    }
    
    /**
     * Get the class name of a Gear
     * 
     * @param string $name
     * @return string
     * @throws \Airship\Alerts\GearNotFound
     */
    public static function getName(string $name)
    {
        $state = \Airship\Engine\State::instance();
        if (!isset($state->gears[$name])) {
            throw new \Airship\Alerts\GearNotFound($name);
        }
        $gears = $state->gears;
        return $gears[$name];
    }
    
    /**
     * Set up initial classes
     * 
     * @param array $gears
     * @throws \Airship\Alerts\GearNotFound
     */
    public static function init(array $gears = [])
    {
        foreach ($gears as $index => $type) {
            self::forge($index, $type);
        }
    }
    
    /**
     * Load a file in a way that doesn't allow access to the parent method's
     * internal functions
     * 
     * @param string $file
     * @return boolean
     */
    protected function sandboxRequire(string $file)
    {
        return (require $file) === 1;
    }
}
