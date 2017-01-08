<?php
declare(strict_types=1);
namespace Airship\Engine;

use Airship\Alerts\{
    GearNotFound,
    Router\LandingComplete,
    Security\SecurityAlert
};
use Airship\Engine\Bolt\{
    Common as CommonBolt,
    Cache as CacheBolt,
    Log as LogBolt,
    Security as SecurityBolt
};
use Airship\Engine\Contract\DBInterface;
use Airship\Engine\Networking\HTTP\{
    ServerRequest,
    Stream,
    Uri
};
use Airship\Engine\Security\{
    CSRF,
    Filter\InputFilterContainer
};
use ParagonIE\CSPBuilder\CSPBuilder;
use ParagonIE\Halite\{
    Alerts\InvalidType,
    Util
};
use ParagonIE\HPKPBuilder\HPKPBuilder;
use Psr\Http\Message\{
    RequestInterface,
    ResponseInterface,
    ServerRequestInterface,
    StreamInterface
};
use Psr\Log\LogLevel;

/**
 * Class Landing
 *
 * For MVC developers, this is analogous to a Controller
 *
 * @package Airship\Engine
 */
class Landing
{
    use CommonBolt;
    use CacheBolt;
    use LogBolt;
    use SecurityBolt;

    const DEFAULT_LONGTERMAUTH_EXPIRE = 2592000; // 30 days

    /**
     * @var string
     */
    protected $airship_http_method;

    /**
     * @var array
     */
    protected $airship_config = [];

    /**
     * @var string
     */
    protected $airship_cabin_prefix;

    /**
     * @var CSRF
     */
    protected $airship_csrf;

    /**
     * @var DBInterface[][]
     */
    protected $airship_databases;

    /**
     * @var Lens
     */
    protected $airship_lens_object;

    /**
     * @var array
     */
    protected $airship_lens_override = [];

    /**
     * @var ResponseInterface
     */
    protected $airship_response;

    /**
     * @var RequestInterface
     */
    protected $airship_request;

    /**
     * @var array
     */
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
     * @param ServerRequestInterface $request
     */
    final public function airshipEjectFromCockpit(
        Lens $lens,
        array $databases = [],
        string $urlPrefix = '',
        ServerRequestInterface $request
    ) {
        $this->airship_request = $request;
        $this->airship_http_method = $_SERVER['REQUEST_METHOD']
                ??
            $this->airship_request->getServerParams()['REQUEST_METHOD'];
        $this->airship_lens_object = $lens;
        $this->airship_databases = $databases;
        $this->airship_csrf = Gears::get('CSRF');
        $this->airship_cabin_prefix = \rtrim('/' . $urlPrefix, '/');
        $file = ROOT . '/config/Cabin/' . \CABIN_NAME . '/config.json';
        if (\file_exists($file)) {
            $this->airship_config = \Airship\loadJSON($file);
        }
        if (empty($request)) {
            $reqGear = Gears::getName('ServerRequest');
            if (IDE_HACKS) {
                $reqGear = new ServerRequest('', new Uri(''));
            }
            $request = $reqGear::fromGlobals();
        }
        $this->airship_response = Gears::get('HTTPResponse');
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
     * Choose a database. We don't do anything fancy, but a Gear
     * might decide to do something different.
     *
     * @return Contract\DBInterface
     */
    protected function airshipChooseDB(): DBInterface
    {
        return \Airship\get_database();
    }

    /**
     * Grab a blueprint
     *
     * @param string $name
     * @param mixed[] ...$cArgs Constructor arguments
     * @return Blueprint
     * @throws InvalidType
     */
    protected function blueprint(string $name, ...$cArgs): Blueprint
    {
        if (!empty($cArgs)) {
            $cache = Util::hash($name . ':' . \json_encode($cArgs));
        } else {
            $cArgs = [];
            $cache = Util::hash($name . '[]');
        }

        if (!isset($this->_cache['blueprints'][$cache])) {
            // CACHE MISS. We need to build it, then!
            $db = $this->airshipChooseDB();
            if ($db instanceof DBInterface) {
                \array_unshift($cArgs, $db);
            }

            try {
                $class = Gears::getName('Blueprint__' . $name);
            } catch (GearNotFound $ex) {
                if ($name[0] === '\\') {
                    // If you pass a \Absolute\Namespace, we will just use it.
                    $class = $name;
                } else {
                    // We default to \Current\Application\Namespace\Blueprint\NameHere.
                    $x = \explode('\\', $this->getNamespace());
                    \array_pop($x);

                    $class = \implode('\\', $x) . '\\Blueprint\\' . $name;
                }
            }
            $this->_cache['blueprints'][$cache] = new $class(...$cArgs);
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
    public function config(string $search = '')
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
     * Render a Lens as text, return a string
     *
     * @param string $name
     * @param mixed[] ...$cArgs Constructor arguments
     * @return string
     */
    protected function getLensAsText(string $name, ...$cArgs): string
    {
        return $this->airship_lens_object->render($name, ...$cArgs);
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
     * @return ResponseInterface
     */
    public function getResponseObject(): ResponseInterface
    {
        return $this->airship_response;
    }

    /**
     * Render a lens, return its contents, don't exit.
     *
     * @param string $name
     * @param mixed[] ...$cArgs Constructor arguments
     * @return string
     */
    protected function lensRender(string $name, ...$cArgs): string
    {
        if (isset($this->airship_lens_override[$name])) {
            $name = $this->airship_lens_override[$name];
        }
        \ob_start();
        $this->airship_lens_object->display($name, ...$cArgs);
        return \ob_get_clean();
    }

    /**
     * Render a template and terminate execution. Do not cache.
     *
     * @param string $name
     * @param mixed[] ...$cArgs Constructor arguments
     * @throws LandingComplete
     */
    protected function lens(string $name, ...$cArgs)
    {
        if (isset($this->airship_lens_override[$name])) {
            $name = $this->airship_lens_override[$name];
        }
        \ob_start();
        $this->airship_lens_object->display($name, ...$cArgs);
        $this->setBodyAndStandardHeaders(
            Stream::fromString(\ob_get_clean())
        );
        throw new LandingComplete();
    }

    /**
     * Override a lens with a different lens. (Meant for Gadgets.)
     *
     * @param string $oldLens
     * @param string $newLens
     */
    protected function overrideLens(string $oldLens, string $newLens)
    {
        $this->airship_lens_override[$oldLens] = $newLens;
    }

    /**
     * Grab post data, but only if the CSRF token is valid
     *
     * @param InputFilterContainer $filterContainer - Type filter for POST data
     * @param bool $ignoreCSRFToken - Don't validate CSRF tokens
     *
     * @return array|bool
     * @throws SecurityAlert
     */
    protected function post(
        InputFilterContainer $filterContainer = null,
        bool $ignoreCSRFToken = false
    ) {
        if ($this->airship_http_method !== 'POST' || empty($_POST)) {
            return false;
        }
        if ($ignoreCSRFToken) {
            if ($filterContainer) {
                try {
                    return $filterContainer($_POST);
                } catch (\TypeError $ex) {
                    $this->log(
                        'Input validation threw a TypeError',
                        LogLevel::ALERT,
                        \Airship\throwableToArray($ex)
                    );
                    return false;
                }
            }
            return $_POST;
        }

        if ($this->airship_csrf->check()) {
            if ($filterContainer) {
                try {
                    return $filterContainer($_POST);
                } catch (\TypeError $ex) {
                    $this->log(
                        'Input validation threw a TypeError',
                        LogLevel::ALERT,
                        \Airship\throwableToArray($ex)
                    );
                    return false;
                }
            }
            return $_POST;
        }
        $state = State::instance();
        if ($state->universal['debug']) {
            // This is only thrown during development, to be noisy.
            throw new SecurityAlert(
                \__('CSRF validation failed')
            );
        }
        $this->log('CSRF validation failed', LogLevel::ALERT);
        return false;
    }

    /**
     * Render lens content, cache it, then display it.
     *
     * @param string $name
     * @param array $cArgs Constructor arguments
     * @return bool
     * @throws LandingComplete
     */
    protected function stasis(string $name, ...$cArgs): bool
    {
        // We don't want to cache anything tied to a session.
        $oldSession = $_SESSION;
        $_SESSION = [];
        $data = $this->airship_lens_object->render($name, ...$cArgs);
        $_SESSION = $oldSession;

        $port = $_SERVER['HTTP_PORT'] ?? '';
        $cacheKey = $_SERVER['HTTP_HOST'] . ':' . $port . '/' . $_SERVER['REQUEST_URI'];
        if (!$this->airship_filecache_object->set($cacheKey, $data)) {
            return false;
        }

        $state = State::instance();
        if ($state->CSP instanceof CSPBuilder) {
            $this->airship_cspcache_object->set(
                $_SERVER['REQUEST_URI'],
                \json_encode($state->CSP->getHeaderArray())
            );
        }
        $this->setBodyAndStandardHeaders(
            Stream::fromString($data)
        );
        throw new LandingComplete();
    }

    /**
     * @param StreamInterface $stream
     * @return Landing
     */
    protected function setBodyAndStandardHeaders(StreamInterface $stream): self
    {
        $state = State::instance();
        $this->airship_response = $this->airship_response
            ->withHeader('Content-Type', 'text/html;charset=UTF-8')
            ->withHeader('Content-Language', $state->lang)
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'SAMEORIGIN')
            ->withHeader('X-XSS-Protection', '1; mode=block')
            ->withBody($stream);
        if ($state->CSP instanceof CSPBuilder) {
            $this->airship_response = $state->CSP->injectCSPHeader(
                $this->airship_response
            );
        }
        if ($state->HPKP instanceof HPKPBuilder) {
            list($hpkp_n, $hpkp_v) = \explode(':', $state->HPKP->getHeader());
            $this->airship_response = $this->airship_response
                ->withHeader(
                    $hpkp_n,
                    \trim($hpkp_v)
                );
        }
        return $this;
    }

    /**
     * Grab a lens
     *
     * @param string $name
     * @param mixed $value
     * @return Lens
     */
    protected function storeLensVar(string $name, $value): Lens
    {
        return $this->airship_lens_object->store($name, $value);
    }

    /**
     * @param string $name
     * @return bool
     */
    protected function setActiveMotif(string $name): bool
    {
        return $this->airship_lens_object->setActiveMotif($name);
    }
}
