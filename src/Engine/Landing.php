<?php
declare(strict_types=1);
namespace Airship\Engine;

use \ParagonIE\CSPBuilder\CSPBuilder;
use \ParagonIE\Halite\Alerts\InvalidType;
use \ParagonIE\Halite\Util;
use \Airship\Engine\Contract\DBInterface;
use \Airship\Engine\Bolt\{
    Common as CommonBolt,
    FileCache as FileCacheBolt,
    Log as LogBolt,
    Security as SecurityBolt
};

/**
 * Class Landing
 * @package Airship\Engine
 *
 * For MVC developers, this is analogous to a Controller
 */
class Landing
{
    use CommonBolt;
    use FileCacheBolt;
    use LogBolt;
    use SecurityBolt;

    const DEFAULT_LONGTERMAUTH_EXPIRE = 2592000; // 30 days

    protected $airship_http_method;
    protected $airship_config = [];
    protected $airship_cabin_prefix;
    protected $airship_csrf;
    protected $airship_databases;
    protected $airship_lens_object;
    protected $_cache = [
        'blueprints' => [],
        'lenses' => []
    ];
    
    /**
     * Dependency injection aside from the controller. Allows you to write your
     * own constructors.
     * 
     * This is final so nobody changes it in a Gear. Please don't mess with 
     * this component.
     * 
     * @param Lens $lens
     * @param array $databases
     * @param string $urlPrefix
     */
    final public function airshipEjectFromCockpit(
        Lens $lens,
        array $databases = [],
        string $urlPrefix = ''
    ) {
        $this->airship_http_method = $_SERVER['REQUEST_METHOD'];
        $this->airship_lens_object = $lens;
        $this->airship_databases = $databases;
        $this->airship_csrf = Gears::get('CSRF');
        $this->airship_cabin_prefix = '/' . $urlPrefix;
        $file = ROOT . '/config/Cabin/' . \CABIN_NAME . '/config.json';
        if (\file_exists($file)) {
            $this->airship_config = \Airship\loadJSON($file);
        }
        $this->airshipLand();
    }
    
    /**
     * Overloadable; invoked after airshipEjectFromCockpit()
     * 
     * This is typically what you want to overload in place of a constructor.
     */
    public function airshipLand()
    {
        // Do nothing. 
    }

    /**
     * @return mixed
     */
    public function resetBaseTemplate()
    {
        return $this->airship_lens_object->resetBaseTemplate();
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function setBaseTemplate(string $name)
    {
        return $this->airship_lens_object->setBaseTemplate($name);
    }

    /**
     * Add a filter to the lens
     *
     * @param string $name
     * @param callable $func
     */
    protected function addLensFilter(string $name, callable $func)
    {
        $this->airship_lens_object->filter($name, $func);
    }

    /**
     * Add a function to the lens
     *
     * @param string $name
     * @param callable $func
     */
    protected function addLensFunction(string $name, callable $func)
    {
        $this->airship_lens_object->func($name, $func);
    }

    /**
     * Choose a database
     *
     * @return Contract\DBInterface
     */
    protected function airshipChooseDB(): DBInterface
    {
        if (\array_key_exists('default', $this->airship_databases)) {
            $db = $this->airship_databases['default'];
            if (\count($db) === 1) {
                return \array_shift($db);
            }
            $k = \array_keys($db);
            $r = $k[random_int(0, \count($db) - 1)];
            return $db[$r];
        } elseif (count($this->airship_databases) === 1) {
            $k = \array_keys($this->airship_databases);
            $db = $this->airship_databases[\array_shift($k)];
            if (\count($db) === 1) {
                return \array_shift($db);
            }
            $_k = \array_keys($db);
            $r = $_k[random_int(0, \count($db) - 1)];
            return $db[$r];
        }
        return null;
    }

    /**
     * Grab a blueprint
     *
     * @param string $name
     * @param mixed[] ...$cargs Constructor arguments
     * @return Blueprint
     * @throws InvalidType
     */
    protected function blueprint(string $name, ...$cargs): Blueprint
    {
        if (!empty($cargs)) {
            $cache = Util::hash($name.':'.\json_encode($cargs));
        } else {
            $cargs = array();
            $cache = Util::hash($name.'[]');
        }

        if (!isset($this->_cache['blueprints'][$cache])) {
            // CACHE MISS. We need to build it, then!
            $db = $this->airshipChooseDB();
            if ($db instanceof DBInterface) {
                \array_unshift($cargs, $db);
            }

            if ($name[0] === '\\') {
                // If you pass a \Absolute\Namespace, we will just use it.
                $class = $name;
            } else {
                // We default to \Current\Application\Namespace\Blueprint\NameHere.
                $x = \explode('\\', $this->getNamespace());
                \array_pop($x);

                $class = \implode('\\', $x).'\\Blueprint\\'.$name;
            }
            $this->_cache['blueprints'][$cache] = new $class(...$cargs);
            if (!($this->_cache['blueprints'][$cache] instanceof Blueprint)) {
                throw new InvalidType(
                    \trk('errors.type.wrong_class', 'Blueprint')
                );
            }
            \Airship\tightenBolts($this->_cache['blueprints'][$cache]);
        }
        return $this->_cache['blueprints'][$cache];
    }

    /**
     * Get configuration settings
     *
     * @param string $search
     * @return mixed
     */
    protected function config(string $search = '')
    {
        if (empty($search)) {
            return $this->airship_config;
        }
        $search = \explode('.', $search);
        $config = $this->airship_config;
        foreach ($search as $k) {
            if (isset($config[$k])) {
                $config = $config[$k];
            } else {
                return null;
            }
        }
        return $config;
    }

    /**
     * Get the name of the current namespace
     *
     * @return string
     */
    protected function getNamespace(): string
    {
        $current = \get_class($this);
        $reflect = new \ReflectionClass($current);
        return $reflect->getNamespaceName();
    }

    /**
     * Grab a lens
     *
     * @param string $name
     * @param mixed[] ...$cargs Constructor arguments
     * @return bool
     */
    protected function lens(string $name, ...$cargs): bool
    {
        return $this->airship_lens_object->display($name, ...$cargs);
    }

    /**
     * Render a Lens as text, return a string
     *
     * @param string $name
     * @param mixed[] ...$cargs Constructor arguments
     * @return string
     */
    protected function getLensAsText(string $name, ...$cargs): string
    {
        return $this->airship_lens_object->render($name, ...$cargs);
    }

    /**
     * Grab post data, but only if the CSRF token is valid
     *
     * @param bool $ignoreCSRFToken - Don't validate CSRF tokens
     *
     * @return array|bool
     */
    protected function post(bool $ignoreCSRFToken = false)
    {
        if ($this->airship_http_method !== 'POST' || empty($_POST)) {
            return false;
        }
        if ($ignoreCSRFToken) {
            return $_POST;
        }

        if ($this->airship_csrf->check()) {
            return $_POST;
        }
        return false;
    }

    /**
     * Render lens content, cache it, then display it.
     *
     * @param string $name
     * @param array $cargs Constructor arguments
     * @return bool
     */
    protected function stasis(string $name, ...$cargs): bool
    {
        $oldSession = $_SESSION;
        $_SESSION = [];
        $data = $this->airship_lens_object->render($name, ...$cargs);
        $_SESSION = $oldSession;

        $this->airship_filecache_object->set(
            $_SERVER['REQUEST_URI'],
            $data
        );

        $state = State::instance();
        if (!\headers_sent()) {
            \header('Content-Type: text/html;charset=UTF-8');
            \header('Content-Language: '.$state->lang);
            \header('X-Frame-Options: SAMEORIGIN'); // Maybe make this configurable down the line?
            \header('X-XSS-Protection: 1; mode=block');
        }
        $csp = $state->CSP;
        if ($csp instanceof CSPBuilder) {
            $csp->sendCSPHeader();
            $this->airship_cspcache_object->set(
                $_SERVER['REQUEST_URI'],
                \json_encode($csp->getHeaderArray())
            );
        }
        echo $data;
        return true;
    }

    /**
     * Grab a lens
     *
     * @param string $name
     * @param mixed $value
     */
    protected function storeLensVar(string $name, $value)
    {
        return $this->airship_lens_object->store($name, $value);
    }

    /**
     * @param string $name
     * @return mixed
     */
    protected function setActiveMotif(string $name)
    {
        return $this->airship_lens_object->setMotif($name);
    }
}
