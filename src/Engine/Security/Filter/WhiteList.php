<?php
declare(strict_types=1);
namespace Airship\Engine\Security\Filter;

/**
 * Class WhiteList
 * @package Airship\Engine\Security\Filter
 */
class WhiteList extends InputFilter
{
    /**
     * @var array
     */
    protected $allowedValues = [];

    /**
     * WhiteList constructor.
     * @param array ...$values
     */
    public function __construct(...$values)
    {
        $this->addToWhiteList(...$values);
    }

    /**
     * @param array ...$values
     * @return $this
     */
    protected function addToWhiteList(...$values)
    {
        switch ($this->type) {
            case 'bool':
                foreach ($values as $val) {
                    $this->allowedValues []= (bool) $val;
                }
                break;
            case 'float':
                foreach ($values as $val) {
                    $this->allowedValues []= (float) $val;
                }
                break;
            case 'int':
                foreach ($values as $val) {
                    $this->allowedValues []= (int) $val;
                }
                break;
            case 'string':
                foreach ($values as $val) {
                    $this->allowedValues []= (string) $val;
                }
                break;
            default:
                foreach ($values as $val) {
                    $this->allowedValues []= $val;
                }
        }
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
        if (!empty($this->allowedValues)) {
            if (!\in_array($data, $this->allowedValues)) {
                $data = null;
            }
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
}
