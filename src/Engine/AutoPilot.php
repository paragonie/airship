<?php
declare(strict_types=1);
namespace Airship\Engine;

use Airship\Alerts\GearNotFound;
use Airship\Alerts\Router\{
    EmulatePageNotFound,
    FallbackLoop,
    ControllerComplete
};
use Airship\Engine\Contract\RouterInterface;
use Airship\Engine\Security\Util;

use ParagonIE\ConstantTime\Binary;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class AutoPilot
 *
 * RESTful Routing for the Airship
 *
 * @package Airship\Engine
 */
class AutoPilot implements RouterInterface
{
    /**
     * @var string Current request path
     */
    public static $mypath = '*';

    /**
     * @var string
     */
    public static $path = '*';

    /**
     * @var string
     */
    public static $active_cabin;

    /**
     * @var string
     */
    public static $patternPrefix = '';

    /**
     * @var string
     */
    public static $cabinIndex;

    /**
     * @var string Escaped
     */
    protected static $httpHost = '';

    /**
     * @var array
     */
    protected $cabin = [];

    /**
     * @var Controller|null
     */
    protected $landing = null;

    /**
     * @var View
     */
    protected $view;

    /**
     * @var Database[]
     */
    protected $databases;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * AutoPilot constructor.
     *
     * @param array $cabin
     * @param View $lens (optional)
     * @param Database[] $databases (optional)
     */
    public function __construct(
        array $cabin,
        View $lens,
        array $databases = []
    ) {
        $this->cabin = $cabin;
        $this->view = $lens;
        $this->databases = $databases;
    }
    
    /**
     * Set the active cabin
     * 
     * @param array $cabin
     * @param string $prefix
     * @return self
     */
    public function setActiveCabin(array $cabin, string $prefix): self
    {
        self::$active_cabin = $cabin['namespace'] ?? $cabin['name'];
        self::$cabinIndex = $prefix;
        if ($prefix === '*') {
            self::$patternPrefix = '';
        } elseif ($prefix[0] === '*') {
            self::$patternPrefix = Binary::safeSubstr($prefix, 2);
        } else {
            $start = \strpos($prefix, '/');
            if ($start !== false) {
                self::$patternPrefix = Binary::safeSubstr($prefix, $start + 1);
            }
        }
        return $this;
    }

    /**
     * @param RequestInterface $request
     * @return self
     */
    public function setRequestObject(RequestInterface $request): self
    {
        $this->request = $request;
        return $this;
    }

    /**
     * Replace {token}s with their regex stand-ins.
     *
     * @param string $string
     * @return string
     */
    public static function makePath(string $string): string
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
     * @return string
     */
    public static function getHttpHost(): string
    {
        return self::$httpHost;
    }

    /**
     * @param string $value
     */
    public static function setHttpHost(string $value): void
    {
        self::$httpHost = Util::charWhitelist(
            $value,
            Util::ALPHANUMERIC . '.-'
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
    ): bool {
        if (empty($uri)) {
            $uri = $_SERVER['REQUEST_URI'] ?? '';
        }
        if (empty($scheme)) {
            $scheme = self::isHTTPSConnection()
                ? 'https'
                : 'http';
        }
        if ($cabinKey === '*') {
            return true;
        }
        if ($cabinKey[0] === '*') {
            if ($cabinKey[1] === '/') {
                // */some_dir/
                $pattern = \preg_quote(Binary::safeSubstr($cabinKey, 2), '#');
                if (\preg_match('#^/'.$pattern.'#', $uri) === 1) {
                    self::setHTTPHost($_SERVER['HTTP_HOST'] ?? 'localhost');
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
                self::setHTTPHost($activeHost);
                return $https_only
                    ? self::forceHTTPS($scheme)
                    : true;
            } elseif ($pos !== false) {
                self::setHTTPHost($activeHost);
                $sub = Binary::safeSubstr($cabinKey, $pos);
                $host = Binary::safeSubstr($cabinKey, 0, $pos);
                if (
                    \strtolower($activeHost) === \strtolower($host) &&
                    \preg_match('#^' . \preg_quote($sub, '#') . '#', $uri)
                ) {
                    return $https_only
                        ? self::forceHTTPS($scheme)
                        : true;
                }
            } elseif (\strtolower($activeHost) === \strtolower($cabinKey)) {
                self::setHTTPHost($activeHost);
                return $https_only
                    ? self::forceHTTPS($scheme)
                    : true;
            }
        }
        return false;
    }

    /**
     * Actually serve the HTTP request
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     * @throws ControllerComplete
     * @throws EmulatePageNotFound
     * @throws FallbackLoop
     * @throws \Error
     * @throws \TypeError
     */
    public function route(ServerRequestInterface $request): ResponseInterface
    {
        $this->request = $request;
        $this->loadInjectedRoutes();
        $args = [];
        foreach ($this->cabin['data']['routes'] as $path => $landing) {
            $path = self::makePath($path);
            if (self::testController($path, $_SERVER['REQUEST_URI'], $args)) {
                self::$mypath = $path;
                self::$path = Binary::safeSubstr(
                    $_SERVER['REQUEST_URI'],
                    Binary::safeStrlen(self::$patternPrefix) + 1
                );
                try {
                    // Attempt to serve the page:
                    return $this->serve($landing, \array_slice($args, 1));
                } catch (EmulatePageNotFound $ex) {
                    // If this exception is throw, we will attempt to serve
                    // the fallback route (which might end up with a 404 page)
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
    public static function testController(
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
     * Which Cabin does this URL belong to?
     *
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
            if (!$cabin['enabled']) {
                continue;
            }
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
     * This loads all of the routes injected by the Gadgets into the current
     * Cabin
     *
     * @return self
     */
    protected function loadInjectedRoutes(): self
    {
        $state = State::instance();
        if (empty($state->injectRoutes)) {
            return $this;
        }
        foreach ($state->injectRoutes as $path => $landing) {
            if (!\array_key_exists($path, $this->cabin['data']['routes'])) {
                $this->cabin['data']['routes'][$path] = $landing;
            }
        }
        return $this;
    }

    /**
     * Actually serve the routes. Called by route() above.
     *
     * @param array $route
     * @param array $args
     * @return ResponseInterface
     * @throws FallbackLoop
     * @throws \Error
     */
    protected function serve(array $route, array $args = []): ResponseInterface
    {
        static $calledOnce = null;
        if (count($route) === 1) {
            $route[] = 'index';
        }

        try {
            $class_name = Gears::getName('Controller__' . $route[0]);
        } catch (GearNotFound $ex) {
            $class_name = '\\Airship\\Cabin\\' . self::$active_cabin . '\\Controller\\' . $route[0];
        }
        $method = $route[1];

        if (!\class_exists($class_name)) {
            $state = State::instance();
            $state->logger->error(
                'Controller Error: Class not found when invoked from router',
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
        /**
         * @var Controller
         */
        $this->landing = new $class_name;
        if (!($this->landing instanceof Controller)) {
            throw new \Error(
                \__("%s is not a Controller", "default", $class_name)
            );
        }
        
        // Dependency injection with a twist
        $this->landing->airshipEjectFromCockpit(
            $this->view,
            $this->databases,
            self::$patternPrefix,
            $this->request
        );

        // Tighten the Bolts!
        \Airship\tightenBolts($this->landing);

        if (!\method_exists($this->landing, $method)) {
            if ($calledOnce) {
                throw new FallbackLoop(
                    \trk('errors.router.fallback_loop')
                );
            }
            $calledOnce = true;
            return $this->serveFallback();
        }

        try {
            $this->landing->$method(...$args);
            return $this->landing->getResponseObject();
        } catch (ControllerComplete $ex) {
            return $this->landing->getResponseObject();
        }
    }

    /**
     * @return Controller
     * @throws \TypeError
     */
    public function getController(): Controller
    {
        if (!($this->landing instanceof Controller)) {
            throw new \TypeError('Invalid type');
        }
        return $this->landing;
    }

    /**
     * @param null|ResponseInterface $response
     * @return void
     */
    public function serveResponse(?ResponseInterface $response = null): void
    {
        if (empty($response)) {
            $response = $this->getController()
                ->getResponseObject();
        }

        // Send headers:
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                \header(\sprintf('%s: %s', $name, $value), false);
            }
        }
        echo (string) $response->getBody();
        exit(0);
    }

    /**
     * This serves the fallback route, if it's defined.
     *
     * The fallback route handles:
     *
     * - Custom pages (if any exist), or
     * - Redirects
     *
     * @return ResponseInterface
     * @throws FallbackLoop
     */
    protected function serveFallback(): ResponseInterface
    {
        // If we're still here, let's try the fallback handler
        if (isset($this->cabin['data']['route_fallback'])) {
            \preg_match(
                '#^/?' . self::$patternPrefix . '/(.*)$#',
                $_SERVER['REQUEST_URI'],
                $args
            );
            try {
                return $this->serve(
                    $this->cabin['data']['route_fallback'],
                    \explode('/', ($args[1] ?? ''))
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
        throw new FallbackLoop('No fallback defined');
    }

    /**
     * Do not allow insecure HTTP request to proceed
     *
     * @param string $scheme
     * @return bool
     */
    protected static function forceHTTPS(string $scheme = ''): bool
    {
        if (!self::isHTTPSConnection($scheme)) {
            // Should we redirect to an HTTPS endpoint?
            \Airship\redirect(
                'https://' . $_SERVER['HTTP_HOST'] . '/' . $_SERVER['REQUEST_URI'],
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
    public static function isHTTPSConnection(string $scheme = ''): bool
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
