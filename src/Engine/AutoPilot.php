<?php
declare(strict_types=1);
namespace Airship\Engine;

use \Airship\Alerts\Router\{
    EmulatePageNotFound,
    FallbackLoop
};
use \Airship\Engine\Contract\RouterInterface;

/**
 * Class AutoPilot
 *
 * RESTful Routing for the Airship
 *
 * @package Airship\Engine
 */
class AutoPilot implements RouterInterface
{
    // Current request path
    public static $mypath = '*';
    public static $path = '*';
    public static $active_cabin;
    public static $patternPrefix = '';

    protected $CSPBuilder;
    protected $cabin = [];
    protected $lens;
    protected $databases;

    /**
     * AutoPilot constructor.
     *
     * @param array $cabin
     * @param Lens $lens (optional)
     * @param Database[] $databases (optional)
     */
    public function __construct(
        array $cabin = [],
        Lens $lens = null,
        array $databases = []
    ) {
        $this->cabin = $cabin;
        $this->lens = $lens;
        $this->databases = $databases;
    }
    
    /**
     * Set the active cabin
     * 
     * @param array $cabin
     * @param string $prefix
     */
    public function setActiveCabin(array $cabin, string $prefix)
    {
        self::$active_cabin = $cabin['name'];
        if ($prefix === '*') {
            self::$patternPrefix = '';
        } elseif ($prefix[0] === '*') {
            self::$patternPrefix = \substr($prefix, 2);
        } else {
            $start = \strpos($prefix, '/');
            if ($start !== false) {
                self::$patternPrefix = \substr($prefix, $start + 1);
            }
        }
    }

    /**
     * Replace {token}s with their regex stand-ins.
     *
     * @param string $string
     * @return string
     */
    public static function makePath(string $string)
    {
        return
            \str_replace([
            // These match (but don't capture) an optional / prefix:
                '{_any}',
                '{_id}',
                '{_year}',
                '{_month}',
                '{_day}',
                '{_isodate}',
                '{_lower}',
                '{_upper}',
                '{_page}',
                '{_slug}',
                '{_uslug}',
                '{_lslug}',
                '{_string}',
                '{_hex}',
            // Without the / prefix:
                '{any}',
                '{id}',
                '{year}',
                '{month}',
                '{day}',
                '{isodate}',
                '{lower}',
                '{upper}',
                '{slug}',
                '{uslug}',
                '{lslug}',
                '{string}',
                '{hex}'
            ], [
            // These match (but don't capture) an optional / prefix:
                '(?:/(.*))?',
                '(?:/([0-9]+))?',
                '(?:/([0-9]{4}))?',
                '(?:/([01][0-9]))?',
                '(?:/([0-9]{4}\-[01][0-9]\-[0-3][0-9]))?',
                '(?:/([0-3][0-9]))?',
                '(?:/([a-z]+))?',
                '(?:/([A-Z]+))?',
                '(?:/([0-9]*))?',
                '(?:/([A-Za-z0-9_\\-]+))?',
                '(?:/([A-Z0-9_\\-]+))?',
                '(?:/([0-9a-z\\-]+))?',
                '(?:/([^/\?]+))?',
                '(?:/([0-9a-fA-F]+))?',
            // Without the / prefix:
                '(.*)',
                '([0-9]+)',
                '([0-9]{4})',
                '([01][0-9])',
                '([0-3][0-9])',
                '([0-9]{4}\-[01][0-9]\-[0-3][0-9])',
                '([a-z]+)',
                '([A-Z]+)',
                '([A-Za-z0-9_\\-]+)',
                '([A-Z0-9_\\-]+)',
                '([0-9a-z\-]+)',
                '([^/\?]+)',
                '([0-9a-fA-F]+)'
            ],
            $string
        );
    }
    
    /**
     * Does a given cabin key match the current HTTP host, port, and path?
     * 
     * @param string $cabinKey
     * @param bool $https_only
     * @param string $scheme
     * @param string $activeHost
     * @param string $uri
     * @return bool
     */
    public static function isActiveCabinKey(
        string $cabinKey = '*',
        bool $https_only = false,
        string $scheme = '',
        string $activeHost = '',
        string $uri = ''
    ) : bool {
        if (empty($uri)) {
            $uri = $_SERVER['REQUEST_URI'] ?? '';
        }
        if (empty($scheme)) {
            $scheme = self::isHTTPSconnection()
                ? 'https'
                : 'http';
        }
        if ($cabinKey === '*') {
            return true;
        }
        if ($cabinKey[0] === '*') {
            if ($cabinKey[1] === '/') {
                // */some_dir/
                $pattern = \preg_quote(\substr($cabinKey, 2), '#');
                if (\preg_match('#^/'.$pattern.'#', $uri) === 1) {
                    return $https_only
                        ? self::forceHTTPS()
                        : true;
                }
            }
        } else {
            if (empty($activeHost)) {
                $activeHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
            }
            $pos = \strpos($cabinKey, '/');
            if ($pos === false && \preg_match('#^'.\preg_quote($cabinKey, '#').'#', $uri)) {
                return $https_only
                    ? self::forceHTTPS($scheme)
                    : true;
            } elseif ($pos !== false) {
                $sub = \substr($cabinKey, $pos);
                $host = \substr($cabinKey, 0, $pos);
                if (
                    \strtolower($activeHost) === \strtolower($host) &&
                    \preg_match('#^'.\preg_quote($sub, '#').'#', $uri)
                ) {
                    return $https_only
                        ? self::forceHTTPS($scheme)
                        : true;
                }
            } elseif (\strtolower($activeHost) === \strtolower($cabinKey)) {
                return $https_only
                    ? self::forceHTTPS($scheme)
                    : true;
            }
        }
        return false;
    }

    /**
     * Actually serve the HTTP request
     */
    public function route()
    {
        $this->loadInjectedRoutes();
        $args = [];
        foreach ($this->cabin['data']['routes'] as $path => $landing) {
            $path = self::makePath($path);
            if (self::testLanding($path, $_SERVER['REQUEST_URI'], $args)) {
                self::$mypath = $path;
                self::$path = \substr($_SERVER['REQUEST_URI'], \strlen(self::$patternPrefix) + 1);
                try {
                    return $this->serve($landing, \array_slice($args, 1));
                } catch (EmulatePageNotFound $ex) {
                    return $this->serveFallback();
                }
            }
        }
        return $this->serveFallback();
    }

    /**
     * Test a path against a URI
     *
     * @param string $path
     * @param string $uri
     * @param array $args
     * @param bool $needsPrep
     * @return bool
     */
    public static function testLanding(
        string $path,
        string $uri,
        array &$args = [],
        bool $needsPrep = false
    ): bool {
        if ($needsPrep) {
            $path = self::makePath($path);
            $prefix = '';
        } else {
            $prefix = self::$patternPrefix;
        }
        if ($path === '') {
            return \preg_match(
                '#^/?' . $prefix . '/?$#',
                $uri,
                $args
            ) > 0;
        }
        return \preg_match(
            '#^/?' . $prefix . '/' . $path . '#',
            $uri,
            $args
        ) > 0;
    }

    /**
     * @param string $url
     * @return string
     */
    public function testCabinForUrl(string $url): string
    {
        $state = State::instance();
        $scheme = \parse_url($url, PHP_URL_SCHEME);
        $hostname = \parse_url($url, PHP_URL_HOST);
        $path = \parse_url($url, PHP_URL_PATH) ?? '/';
        foreach ($state->cabins as $k => $cabin) {
            if (self::isActiveCabinKey(
                $k,
                $cabin['https'] ?? false,
                $scheme,
                $hostname,
                $path
            )) {
                return $cabin['name'];
            }
        }
        return '';
    }

    /**
     * See Gadgets::injectRoutes()
     *
     * This loads all of the routes injected by the Gadgets into the current cabin
     */
    protected function loadInjectedRoutes()
    {
        $state = State::instance();
        if (empty($state->injectRoutes)) {
            return;
        }
        foreach ($state->injectRoutes as $path => $landing) {
            if (!\array_key_exists($path, $this->cabin['data']['routes'])) {
                $this->cabin['data']['routes'][$path] = $landing;
            }
        }
    }

    /**
     * Actually serve the routes
     * 
     * @param array $route
     * @param array $args
     * @return mixed
     * @throws FallbackLoop
     */
    protected function serve(array $route, array $args = [])
    {
        static $calledOnce = null;
        if (count($route) === 1) {
            $route[] = 'index';
        }
        
        $class_name = '\\Airship\\Cabin\\'.self::$active_cabin.'\\Landing\\'.$route[0];
        $method = $route[1];

        if (!\class_exists($class_name)) {
            $state = State::instance();
            $state->logger->error(
                'Landing Error: Class not found when invoked from router',
                [
                    'route' => [
                        'class' => $class_name,
                        'method' => $method
                    ]
                ]
            );
            $calledOnce = true;
            return $this->serveFallback();
        }
        
        // Load our cabin-specific landing
        $landing = new $class_name;

        if (IDE_HACKS) {
            $landing = new Landing();
        }
        
        // Dependency injection with a twist
        $landing->airshipEjectFromCockpit(
            $this->lens,
            $this->databases,
            self::$patternPrefix
        );
        
        \Airship\tightenBolts($landing);

        if (!\method_exists($landing, $method)) {
            if ($calledOnce) {
                throw new FallbackLoop(
                    \trk('errors.router.fallback_loop')
                );
            }
            $calledOnce = true;
            return $this->serveFallback();
        }

        return $landing->$method(...$args);
    }

    /**
     * @return mixed
     */
    protected function serveFallback()
    {
        // If we're still here, let's try the fallback handler
        if (isset($this->cabin['data']['route_fallback'])) {
            \preg_match('#^/?' . self::$patternPrefix . '/(.*)$#', $_SERVER['REQUEST_URI'], $args);
            try {
                return $this->serve(
                    $this->cabin['data']['route_fallback'],
                    \explode('/', $args[1] ?? '')
                );
            } catch (FallbackLoop $e) {
                $state = State::instance();
                $state->logger->error(
                    'Missing route definition',
                    [
                        'exception' => \Airship\throwableToArray($e)
                    ]
                );
                // We only catch this one
            }
        }

        // If we don't have a fallback handler defined, just give a 404 status and kill the script.
        \header('HTTP/1.1 404 Not Found');
        exit(255);
    }
    
    /**
     * Do not allow insecure HTTP request to proceed
     *
     * @param string $scheme
     * @return bool
     */
    protected static function forceHTTPS(string $scheme = ''): bool
    {
        if (!self::isHTTPSconnection($scheme)) {
            // Should we redirect to an HTTPS endpoint?
            \Airship\redirect(
                'https://'.$_SERVER['HTTP_HOST'].'/'.$_SERVER['REQUEST_URI'],
                $_GET ?? []
            );
        }
        return true;
    }
    
    /**
     * Is this user currently connected over HTTPS?
     *
     * @param string $scheme
     * @return bool
     */
    protected static function isHTTPSconnection(string $scheme = ''): bool
    {
        if (empty($scheme)) {
            $scheme = $_SERVER['HTTPS'] ?? false;
        }
        if (!empty($scheme)) {
            return $scheme !== 'off';
        }
        return false;
    }
}
