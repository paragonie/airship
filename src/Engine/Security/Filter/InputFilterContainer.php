<?php
declare(strict_types=1);
namespace Airship\Engine\Security\Filter;

use \Airship\Engine\Contract\Security\{
    FilterInterface,
    FilterContainerInterface
};

/**
 * Class InputFilterContainer
 *
 * Contains a set of filter rules, useful for enforcing a strict type on
 * unstrucutred data (e.g. HTTP POST parameters).
 *
 * @package Airship\Engine\Security\Filter
 */
abstract class InputFilterContainer implements FilterContainerInterface
{
    /**
     * @var InputFilter[]
     */
    protected $filterMap = [];

    /**
     * InputFilterContainer constructor.
     */
    abstract public function __construct();

    /**
     * Add a new filter to this input value
     *
     * @param string $path
     * @param FilterInterface $filter
     * @return FilterContainerInterface
     */
    public function addFilter(
        string $path,
        FilterInterface $filter
    ): FilterContainerInterface {
        if (!isset($this->filterMap[$path])) {
            $this->filterMap[$path] = [];
        }
        $this->filterMap[$path][] = $filter;
        return $this;
    }

    /**
     * Use firstlevel.second_level.thirdLevel to find indices in an array
     *
     * @param string $key
     * @param mixed $multiDimensional
     * @return mixed
     */
    public function filterValue(string $key, $multiDimensional)
    {
        $pieces = \Airship\chunk($key, '.');
        $filtered =& $multiDimensional;

        /**
         * @security This shouldn't be escapable. We know eval is evil, but
         *           there's not a more elegant way to process this in PHP.
         */
        if (\is_array($multiDimensional)) {
            $var = '$multiDimensional';
            foreach ($pieces as $piece) {
                $append = '[' . self::sanitize($piece) . ']';

                // Alphabetize the parent array
                eval(
                    'if (!isset(' . $var . $append . ')) {' . "\n" .
                    '    ' . $var . $append . ' = null;' . "\n" .
                    '}' . "\n" .
                    '\ksort(' . $var . ');' . "\n"
                );
                $var .= $append;
            }
            eval('$filtered =& ' . $var. ';');
        }

        // If we have filters, let's apply them:
        if (isset($this->filterMap[$key])) {
            foreach ($this->filterMap[$key] as $filter) {
                if ($filter instanceof FilterInterface) {
                    $filtered = $filter->process($filtered);
                }
            }
        }

        return $multiDimensional;
    }

    /**
     * Use firstlevel.second_level.thirdLevel to find indices in an array
     *
     * Doesn't apply filters
     *
     * @param string $key
     * @param array $multiDimensional
     * @return mixed
     */
    public function getUnfilteredValue(string $key, array $multiDimensional = [])
    {
        $pieces = \Airship\chunk($key, '.');
        $value = $multiDimensional;
        foreach ($pieces as $piece) {
            if (!isset($value[$piece])) {
                return null;
            }
            $value = $value[$piece];
        }
        return $value;
    }

    /**
     * Only allow allow printable ASCII characters:
     *
     * @param string $input
     * @return string
     */
    protected static function sanitize(string $input): string
    {
        return \json_encode(
            \preg_replace('#[^\x20-\x7e]#', '', $input)
        );
    }


    /**
     * Process the input array.
     *
     * @param array $dataInput
     * @return array
     */
    public function __invoke(array $dataInput = []): array
    {
        foreach (\array_keys($this->filterMap) as $key) {
            $dataInput = $this->filterValue($key, $dataInput);
        }
        return $dataInput;
    }
}
