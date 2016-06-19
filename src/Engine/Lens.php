<?php
declare(strict_types=1);
namespace Airship\Engine;

use \Airship\Engine\Bolt\Log as LogBolt;
use \ParagonIE\CSPBuilder\CSPBuilder;
use ParagonIE\HPKPBuilder\HPKPBuilder;

/**
 * Class Lens
 *
 * For MVC developers, this is analogous to a View
 *
 * @package Airship\Engine
 */
class Lens
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
     * Lens constructor.
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
        array $params = [], 
        string $mime = 'text/html;charset=UTF-8'
    ): bool {
        $state = State::instance();
        if (!\headers_sent()) {
            \header('Content-Type: '.$mime);
            \header('Content-Language: '.$state->lang);
            \header('X-Frame-Options: SAMEORIGIN'); // Maybe make this configurable down the line?
            \header('X-XSS-Protection: 1; mode=block');
            \ob_start();
            // We need to render this to make sure our CSP headers send!
            echo $this->twigEnv->render(
                $base . '.twig',
                \array_merge($this->stored, $params)
            );
            if (isset($state->HPKP) && $state->HPKP instanceof HPKPBuilder) {
                $state->HPKP->sendHPKPHeader();
            }
            if (isset($state->CSP) && $state->CSP instanceof CSPBuilder) {
                $state->CSP->sendCSPHeader();
            }
            \ob_end_flush();
            return true;
        }
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
        array $params = [],
        string $mime = 'text/html;charset=UTF-8'
    ): bool {
        $state = State::instance();
        if (!\headers_sent()) {
            \header('Content-Type: '.$mime);
            \header('Content-Language: '.$state->lang);
            \header('X-Frame-Options: SAMEORIGIN'); // Maybe make this configurable down the line?
            \header('X-XSS-Protection: 1; mode=block');
            \ob_start();
            // We need to render this to make sure our CSP headers send!
            echo $this->twigEnv->render(
                $file,
                $params
            );
            if (isset($state->HPKP) && $state->HPKP instanceof HPKPBuilder) {
                $state->HPKP->sendHPKPHeader();
            }
            if (isset($state->CSP) && $state->CSP instanceof CSPBuilder) {
                $state->CSP->sendCSPHeader();
            }
            \ob_end_flush();
            return true;
        }
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
        $state = State::instance();
        if (!\headers_sent()) {
            \header("Content-Type: text/html;charset=UTF-8");
            \header("Content-Language: ".$state->lang);
            \header('X-Frame-Options: SAMEORIGIN'); // Maybe make this configurable down the line?
            \header('X-XSS-Protection: 1; mode=block');
            if (isset($state->CSP) && $state->CSP instanceof CSPBuilder) {
                $state->CSP->sendCSPHeader();
            }
            if (isset($state->HPKP) && $state->HPKP instanceof HPKPBuilder) {
                $state->HPKP->sendHPKPHeader();
            }
        }
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
     * @return Lens
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
     * @return Lens
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
     * @return Lens
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
     * @return Lens
     */
    public function func(
        string $name,
        $func = null,
        $is_safe = ['html']
    ): self {
        if (empty($func)) {
            $func = '\\Airship\\LensFunctions\\'.$name;
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
     * @param &array $value Reference to the value
     * @return Lens
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
     * @return Lens
     */
    public function filter(
        string $name,
        $func = null
    ): self {
        if (empty($func)) {
            $func = '\\Airship\\LensFunctions\\'.$name;
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
     * @param &mixed $value
     * @return Lens
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
     * @return \Twig_FilterInterface[]
     */
    public function listFilters(): array
    {
        $filters = $this->twigEnv->getFilters();
        return \array_keys($filters);
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
     * @return Lens
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
     * @return Lens
     */
    public function setBaseTemplate(string $name): self
    {
        $state = State::instance();
        if (isset($state->motifs[$name]) && isset($state->motifs[$name]['config']['base_template'])) {
            $state->base_template = 'motif/' .
                $name .
                '/lens/' .
                $state->motifs[$name]['config']['base_template'] .
                '.twig';
        }
        return $this;
    }
}
