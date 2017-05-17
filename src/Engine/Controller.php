<?php
declare(strict_types=1);
namespace Airship\Engine;

use Airship\Alerts\{
    GearNotFound,
    Router\ControllerComplete,
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
    Response, ServerRequest, Stream, Uri
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
    ResponseInterface,
    ServerRequestInterface,
    StreamInterface
};
use Psr\Log\LogLevel;

/**
 * Class Controller
 *
 * For MVC developers, this is analogous to a Controller
 *
 * @package Airship\Engine
 */
class Controller
{
    use CommonBolt;
    use CacheBolt;
    use LogBolt;
    use SecurityBolt;

    /**
     * @var string
     */
    protected $airship_http_method = '';

    /**
     * @var string
     */
    protected $airship_cabin_prefix = '';

    /**
     * @var CSRF|null
     */
    protected $airship_csrf = null;

    /**
     * @var array<string, array<int, Database>>
     */
    protected $airship_databases = [];

    /**
     * @var View|null
     */
    protected $airship_view_object = null;

    /**
     * @var array
     */
    protected $airship_view_override = [];

    /**
     * @var ResponseInterface|null
     */
    protected $airship_response = null;

    /**
     * @var ServerRequestInterface|null
     */
    protected $airship_request = null;

    /**
     * @var array
     */
    protected $_cache = [
        'models' => [],
        'lenses' => []
    ];
    
    /**
     * Dependency injection aside from the controller. Allows you to write your
     * own constructors.
     * 
     * This is final so nobody changes it in a Gear. Please don't mess with 
     * this component.
     * 
     * @param View $view
     * @param array<string, array<int, Database>> $databases
     * @param string $urlPrefix
     * @param ServerRequestInterface $request
     * @return void
     */
    final public function airshipEjectFromCockpit(
        View $view,
        array $databases = [],
        string $urlPrefix = '',
        ServerRequestInterface $request = null
    ) {
        if (empty($request)) {
            /**
             * @var ServerRequest
             */
            $reqGear = Gears::getName('ServerRequest');
            if (IDE_HACKS) {
                $reqGear = new ServerRequest('', new Uri(''));
            }
            $request = $reqGear::fromGlobals();
        }
        $this->airship_request = $request;
        $this->airship_http_method = $_SERVER['REQUEST_METHOD']
                ??
            $this->airship_request->getServerParams()['REQUEST_METHOD'];
        $this->airship_view_object = $view;
        $this->airship_databases = $databases;
        $this->airship_csrf = Gears::get('CSRF');
        $this->airship_cabin_prefix = \rtrim('/' . $urlPrefix, '/');
        $file = ROOT . '/config/Cabin/' . \CABIN_NAME . '/config.json';
        if (\file_exists($file)) {
            $this->airship_config = \Airship\loadJSON($file);
        }
        $this->airship_response = Gears::get('HTTPResponse');
        $this->includeStandardHeaders();
        $this->airshipLand();
    }
    
    /**
     * Overloadable; invoked after airshipEjectFromCockpit()
     * 
     * This is typically what you want to overload in place of a constructor.
     * @return void
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
        return $this->getAirshipViewObject()->resetBaseTemplate();
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function setBaseTemplate(string $name)
    {
        return $this->getAirshipViewObject()->setBaseTemplate($name);
    }

    /**
     * Add a filter to the lens
     *
     * @param string $name
     * @param callable $func
     * @return self
     */
    protected function addViewFilter(string $name, callable $func): self
    {
        $this->getAirshipViewObject()->filter($name, $func);
        return $this;
    }

    /**
     * Add a function to the lens
     *
     * @param string $name
     * @param callable $func
     * @return self
     */
    protected function addViewFunction(string $name, callable $func): self
    {
        $this->getAirshipViewObject()->func($name, $func);
        return $this;
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
     * @return View
     * @throws \TypeError
     */
    protected function getAirshipViewObject(): View
    {
        if ($this->airship_view_object instanceof View) {
            return $this->airship_view_object;
        }
        throw new \TypeError('View object not defined');
    }

    /**
     * Render a View as text, return a string
     *
     * @param string $name
     * @param mixed[] ...$cArgs Constructor arguments
     * @return string
     */
    protected function getViewAsText(string $name, ...$cArgs): string
    {
        return $this->getAirshipViewObject()->render($name, ...$cArgs);
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
        if (!$this->airship_response instanceof ResponseInterface) {
            $this->airship_response = new Response();
        }
        return $this->airship_response;
    }

    /**
     * @param ResponseInterface $response
     *
     * @return self
     */
    public function replaceResponseObject(ResponseInterface $response): self
    {
        $this->airship_response = $response;
        return $this;
    }

    /**
     * Grab a model
     *
     * @param string $name
     * @param mixed[] ...$cArgs Constructor arguments
     * @return Model
     * @throws InvalidType
     */
    protected function model(string $name, ...$cArgs): Model
    {
        if (!empty($cArgs)) {
            $cache = Util::hash($name . ':' . \json_encode($cArgs));
        } else {
            $cArgs = [];
            $cache = Util::hash($name . '[]');
        }

        if (!isset($this->_cache['models'][$cache])) {
            // CACHE MISS. We need to build it, then!
            /**
             * @var Database
             */
            $db = $this->airshipChooseDB();
            if ($db instanceof Database) {
                \array_unshift($cArgs, $db);
            }

            try {
                $class = Gears::getName('Model__' . $name);
            } catch (GearNotFound $ex) {
                if ($name[0] === '\\') {
                    // If you pass a \Absolute\Namespace, we will just use it.
                    $class = $name;
                } else {
                    // We default to \Current\Application\Namespace\Model\NameHere.
                    $x = \explode('\\', $this->getNamespace());
                    \array_pop($x);

                    $class = \implode('\\', $x) . '\\Model\\' . $name;
                }
            }
            $this->_cache['models'][$cache] = new $class(...$cArgs);
            if (!($this->_cache['models'][$cache] instanceof Model)) {
                throw new InvalidType(
                    \trk('errors.type.wrong_class', 'Model')
                );
            }
            \Airship\tightenBolts($this->_cache['models'][$cache]);
        }
        return $this->_cache['models'][$cache];
    }

    /**
     * Override a lens with a different lens. (Meant for Gadgets.)
     *
     * @param string $oldView
     * @param string $newView
     * @return self
     */
    protected function overrideView(string $oldView, string $newView): self
    {
        $this->airship_view_override[$oldView] = $newView;
        return $this;
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

        if (!($this->airship_csrf instanceof CSRF)) {
            return false;
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
     * @param array $params
     * @param string $mimeType
     *
     * @return bool
     * @throws ControllerComplete
     */
    protected function stasis(string $name, array $params = [], string $mimeType = 'text/html;charset=UTF-8'): bool
    {
        // We don't want to cache anything tied to a session.
        $oldSession = $_SESSION;
        $_SESSION = [];
        $data = $this->getAirshipViewObject()->render($name, $params);
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
            Stream::fromString($data),
            $mimeType
        );
        throw new ControllerComplete();
    }

    /**
     * @param StreamInterface $stream
     * @param string $mimeType
     *
     * @return self
     */
    protected function setBodyAndStandardHeaders(
        StreamInterface $stream,
        string $mimeType = 'text/html;charset=UTF-8'
    ): self {
        /**
         * @var ResponseInterface
         */

        $this->airship_response = $this
            ->includeStandardHeaders($mimeType)
            ->withBody($stream);
        return $this;
    }

    /**
     * @param string $mimeType
     *
     * @return ResponseInterface
     */
    protected function includeStandardHeaders(string $mimeType = 'text/html;charset=UTF-8'): ResponseInterface
    {
        static $secHeaders = false;

        $state = State::instance();

        $response = $this->getResponseObject();
        foreach (\Airship\get_standard_headers($mimeType) as $left => $right) {
            $response = $response->withAddedHeader($left, $right);
        };
        $this->airship_response = $response;

        if (!$secHeaders) {
            if ($state->CSP instanceof CSPBuilder) {
                /**
                 * @var ResponseInterface
                 */
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
            $secHeaders = true;
        }
        return $this->airship_response;
    }

    /**
     * Grab a lens
     *
     * @param string $name
     * @param mixed $value
     * @return View
     */
    protected function storeViewVar(string $name, $value): View
    {
        return $this->getAirshipViewObject()->store($name, $value);
    }

    /**
     * @param string $name
     * @return bool
     */
    protected function setActiveMotif(string $name): bool
    {
        return $this->getAirshipViewObject()->setActiveMotif($name);
    }


    /**
     * Render a lens, return its contents, don't exit.
     *
     * @param string $name
     * @param mixed[] ...$cArgs Constructor arguments
     * @return string
     */
    protected function viewRender(string $name, ...$cArgs): string
    {
        if (isset($this->airship_view_override[$name])) {
            $name = $this->airship_view_override[$name];
        }
        \ob_start();
        $this->getAirshipViewObject()->display($name, ...$cArgs);
        return (string) \ob_get_clean();
    }

    /**
     * Render a template and terminate execution. Do not cache.
     *
     * @param string $name
     * @param array $params
     * @param string $mimeType
     *
     * @throws ControllerComplete
     * @return void
     */
    protected function view(string $name, array $params = [], string $mimeType = 'text/html;charset=UTF-8'): void
    {
        if (isset($this->airship_view_override[$name])) {
            $name = $this->airship_view_override[$name];
        }
        \ob_start();
        $this->getAirshipViewObject()->display($name, $params);
        $this->setBodyAndStandardHeaders(
            Stream::fromString((string) \ob_get_clean()),
            $mimeType
        );
        throw new ControllerComplete();
    }
}
