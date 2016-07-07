<?php
declare(strict_types=1);
namespace Airship\Engine\Security\Filter;

use Airship\Engine\Contract\Security\FilterInterface;
use Airship\Engine\Security\Util;

/**
 * Class InputFilter
 * @package Airship\Engine\Security\Filter
 */
class InputFilter implements FilterInterface
{
    /**
     * @var mixed
     */
    protected $default;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var string (for debugging purposes)
     */
    protected $index;

    /**
     * @var callable[]
     */
    protected $callbacks = [];

    /**
     * Sets the expected input type (e.g. string, boolean)
     *
     * @param string $typeIndicator
     * @return FilterInterface
     */
    public function setType(string $typeIndicator): FilterInterface
    {
        $this->type = $typeIndicator;
        return $this;
    }

    /**
     * Set the default value (not applicable to booleans)
     *
     * @param mixed $value
     * @return FilterInterface
     */
    public function setDefault($value): FilterInterface
    {
        $this->default = $value;
        return $this;
    }

    /**
     * Add a callback to this filter (supports more than one)
     *
     * @param callable $func
     * @return FilterInterface
     */
    public function addCallback(callable $func): FilterInterface
    {
        $this->callbacks[] = $func;
        return $this;
    }

    /**
     * Process data using the filter rules.
     *
     * @param mixed $data
     * @return mixed
     * @throws \TypeError
     */
    public function process($data = null)
    {
        if ($this->type === 'string') {
            if (\is_array($data)) {
                throw new \TypeError(
                    \sprintf('Unexpected array for string filter (%s).', $this->index)
                );
            }
            if (\is_string($data)) {
            } elseif (\is_object($data) && \method_exists($data, '__toString')) {
                $data = (string) $data;
            } elseif (\is_numeric($data)) {
                $data = (string) $data;
            } elseif (\is_null($data)) {
                $data = null;
            } else {
                throw new \TypeError(
                    \sprintf('Expected a string (%s).', $this->index)
                );
            }
        }

        if ($this->type === 'int') {
            if (\is_array($data)) {
                throw new \TypeError(
                    \sprintf('Unexpected array for integer filter (%s).', $this->index)
                );
            }
            if (\is_int($data) || \is_float($data)) {
                $data = (int) $data;
            } elseif (\is_null($data) || $data === '') {
                $data = null;
            } elseif (\is_string($data) && \preg_match('#^\-?[0-9]+$#', $data)) {
                $data = (int) $data;
            } else {
                throw new \TypeError(
                    \sprintf('Expected an integer (%s).', $this->index)
                );
            }
        }

        if ($this->type === 'float') {
            if (\is_array($data)) {
                throw new \TypeError(
                    \sprintf('Unexpected array for float filter (%s).', $this->index)
                );
            }
            if (\is_int($data) || \is_float($data)) {
                $data = (float) $data;
            } elseif (\is_null($data) || $data === '') {
                $data = null;
            } elseif (\is_string($data) && \is_numeric($data)) {
                $data = (float) $data;
            } else {
                throw new \TypeError(
                    \sprintf('Expected an integer or floating point number (%s).', $this->index)
                );
            }
        }

        if ($this->type === 'array' || Util::subString($this->type, -2) === '[]') {
            if (\is_array($data)) {
                $data = (array) $data;
            } elseif (\is_null($data)) {
                $data = [];
            } else {
                throw new \TypeError(
                    \sprintf('Expected an array (%s).', $this->index)
                );
            }
        }

        if ($this->type === 'bool') {
            if (\is_array($data)) {
                throw new \TypeError(
                    \sprintf('Unexpected array for boolean filter (%s).', $this->index)
                );
            }
            $data = !empty($data);
        }

        $data = $this->applyCallbacks($data, 0);
        if ($data === null) {
            $data = $this->default;
        }

        // For type strictness:
        switch ($this->type) {
            case 'bool':
                return (bool) $data;
            case 'float':
                return (float) $data;
            case 'int':
                return (int) $data;
            case 'string':
                return (string) $data;
            default:
                return $data;
        }
    }

    /**
     * Apply all of the callbacks for this filter.
     *
     * @param mixed $data
     * @param int $offset
     * @return mixed
     */
    public function applyCallbacks($data = null, int $offset = 0)
    {
        if (empty($data)) {
            if ($this->type === 'bool') {
                return false;
            }
            return $this->default;
        }
        if ($offset >= \count($this->callbacks)) {
            return $data;
        }
        $func = $this->callbacks[$offset];
        if (\is_callable($func)) {
            $data = $func($data);
        }
        return $this->applyCallbacks($data, $offset + 1);
    }

    /**
     * @param string $index
     * @return FilterInterface
     */
    public function setIndex(string $index): FilterInterface
    {
        $this->index = $index;
        return $this;
    }
}
