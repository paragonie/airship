<?php
declare(strict_types=1);
namespace Airship\Engine;

use Airship\Engine\Bolt\Log as LogBolt;
use Airship\Engine\Networking\HTTP\Response;
use ParagonIE\CSPBuilder\CSPBuilder;
use ParagonIE\HPKPBuilder\HPKPBuilder;
use PHPUnit\Runner\Exception;
use Psr\Http\Message\ResponseInterface;

/**
 * Class View
 *
 * For MVC developers, this is analogous to a View
 *
 * @package Airship\Engine
 */
class View
{
    use LogBolt;

    /**
     * @var \Twig_Environment
     */
    private $twigEnv;

    /**
     * @var array
     */
    private $stored = [];

    /**
     * View constructor.
     * @param \Twig_Environment $twigEnv
     */
    public function __construct(\Twig_Environment $twigEnv)
    {
        $this->twigEnv = $twigEnv;
    }

    /**
     * Render a template and print out its contents.
     *
     * @param string $base  Template to render (e.g. 'index' or 'dir/index')
     * @param array $params Parameters to pass towards the array
     * @param string $mime  MIME type header to transmit
     * @return bool
     */
    public function display(
        string $base, 
        array $params = []
    ): bool {
        echo $this->twigEnv->render(
            $base . '.twig',
            \array_merge($this->stored, $params)
        );
        return true;
    }

    /**
     * Render a template and print out its contents without sanity checks.
     *
     * @param string $file Template to render (e.g. 'index' or 'dir/index')
     * @param array $params Parameters to pass towards the array
     * @param string $mime MIME type
     * @return bool
     */
    public function unsafeDisplay(
        string $file,
        array $params = []
    ): bool {
        echo $this->twigEnv->render(
            $file,
            $params
        );
        return true;
    }

    /**
     * Render a template and return its contents as a string.
     *
     * @param string $base Template to render (e.g. 'index' or 'dir/index')
     * @param array $params Parameters to pass towards the array
     * @return string
     */
    public function render(
        string $base,
        array $params = []
    ): string {
        return $this->twigEnv->render(
            $base . '.twig',
            \array_merge($this->stored, $params)
        );
    }

    /**
     * Render a template and print out its contents without sanity checks.
     *
     * @param string $file Template to render (e.g. 'index' or 'dir/index')
     * @param array $params Parameters to pass towards the array
     * @return string
     */
    public function unsafeRender(
        string $file,
        array $params = []
    ) {
        return $this->twigEnv->render(
            $file,
            $params
        );
    }

    /**
     * Persist state for later rendering.
     *
     * @param string $key Index
     * @param mixed $val Value
     * @return self
     */
    public function store(string $key, $val): self
    {
        $this->stored[$key] = $val;
        return $this;
    }

    /**
     * Maintain an array of values for later rendering, appending the supplied value.
     *
     * @param string $key Index
     * @param mixed $val Value
     * @return self
     */
    public function append(string $key, $val): self
    {
        if (!isset($this->stored[$key])) {
            $this->stored[$key] = [];
        } elseif (!is_array($this->stored[$key])) {
            // Let's store the existing value as the first index
            $this->stored[$key] = [$this->stored[$key]];
        }
        $this->stored[$key] []= $val;
        return $this;
    }

    /**
     * Maintain an array of values for later rendering, prepending the supplied value.
     *
     * @param string $key Index
     * @param mixed $val Value
     * @return self
     */
    public function prepend(string $key, $val): self
    {
        if (!isset($this->stored[$key])) {
            $this->stored[$key] = [];
        } elseif (!is_array($this->stored[$key])) {
            // Let's store the existing value as the first index
            $this->stored[$key] = [$this->stored[$key]];
        }
        \array_unshift($this->stored[$key], $val);
        return $this;
    }

    /**
     * Add a function to our Twig environment
     *
     * @param string $name - Name to access in Twig
     * @param callable $func - function definition
     * @param array $is_safe
     * @return self
     */
    public function func(
        string $name,
        $func = null,
        $is_safe = ['html']
    ): self {
        if (empty($func)) {
            $func = '\\Airship\\ViewFunctions\\' . $name;
        }
        $this->twigEnv->addFunction(
            new \Twig_SimpleFunction(
                $name,
                $func,
                ['is_safe' => $is_safe]
            )
        );
        return $this;
    }

    /**
     * Register an array or object as a Twig global.
     *
     * @param string $name Name to access in Twig (by ref)
     * @param mixed &$value Reference to the value
     * @return self
     */
    public function registerGlobal(
        string $name,
        &$value
    ): self {
        $this->twigEnv->addGlobal($name, $value);
        return $this;
    }

    /**
     * Add a filter to Twig
     *
     * @param string $name - Name to access n Twig
     * @param callable $func - function to apply
     * @return self
     */
    public function filter(
        string $name,
        $func = null
    ): self {
        if (empty($func)) {
            $func = '\\Airship\\ViewFunctions\\' . $name;
        }
        $this->twigEnv->addFilter(
            new \Twig_SimpleFilter($name, $func)
        );
        return $this;
    }
    
    /**
     * Add a global variable to Twig
     * 
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function addGlobal(
        string $key,
        $value
    ): self {
        $this->twigEnv->addGlobal($key, $value);
        return $this;
    }
    
    /**
     * Get all filters
     * 
     * @return array<int, string>
     */
    public function listFilters(): array
    {
        /**
         * If Twig gives us a way to read this information, we'll use that
         * instead.
         *
         * @var array<string, callable>
         */
        /** @noinspection PhpInternalEntityUsedInspection */
        $filters = $this->twigEnv->getFilters();
        /**
         * @var array<int, string>
         */
        $keys = \array_keys($filters);
        return $keys;
    }

    /**
     * Load the cargo for these motifs
     *
     * @param string $name
     * @return self
     */
    public function loadMotifCargo(string $name): self
    {
        $state = State::instance();
        if (
            isset($state->motifs[$name])
                &&
            isset($state->motifs[$name]['config']['cargo'])
        ) {
            $cargoIterator = isset($state->cargoIterator)
                ? $state->cargoIterator
                : [];
            foreach ($state->motifs[$name]['config']['cargo'] as $key => $subPath) {
                $cargoIterator[$key] = 0;
                Gadgets::loadCargo(
                    $key,
                    'motif/' . $name . '/cargo/' . $subPath
                );
            }
            $state->cargoIterator = $cargoIterator;
        }
        return $this;
    }


    /**
     * Load the config for this motif
     *
     * @param string $name
     * @return self
     */
    public function loadMotifConfig(string $name): self
    {
        $state = State::instance();
        if (isset($state->motifs[$name])) {
            try {
                if (\file_exists(ROOT . '/config/motifs/' . $name . '.json')) {
                    $state->motif_config = \Airship\loadJSON(
                        ROOT . '/config/motifs/' . $name . '.json'
                    );
                } else {
                    $state->motif_config = [];
                }
            } catch (\Throwable $ex) {
                $state->motif_config = [];
            }
        }
        return $this;
    }

    /**
     * Set the active motif
     *
     * @param string $name
     * @return bool
     */
    public function setActiveMotif(string $name): bool
    {
        $state = State::instance();
        if (\in_array($name, \array_keys($state->motifs))) {
            $this->stored['active_motif'] = $name;
            $this->setBaseTemplate($name);
            return true;
        }
        return false;
    }

    /**
     * Reset the base template
     *
     * @return self
     */
    public function resetBaseTemplate(): self
    {
        $state = State::instance();
        $state->base_template = 'base.twig';
        return $this;
    }

    /**
     * Override the base template
     *
     * @param string $name
     * @return self
     */
    public function setBaseTemplate(string $name): self
    {
        $state = State::instance();
        if (
            isset($state->motifs[$name])
                &&
            isset($state->motifs[$name]['config']['base_template'])
        ) {
            $state->base_template = 'motif/' .
                $name .
                '/' .
                $state->motifs[$name]['config']['base_template'] .
                '.twig';
        }
        return $this;
    }

    /**
     * @param ResponseInterface $response
     *
     * @return View
     */
    public function setResponseObject(ResponseInterface $response): self
    {
        $this->response = $response;
        return $this;
    }
}
