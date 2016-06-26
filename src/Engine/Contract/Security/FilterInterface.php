<?php
declare(strict_types=1);
namespace Airship\Engine\Contract\Security;

/**
 * Interface FilterInterface
 * @package Airship\Engine\Contract\Security
 */
interface FilterInterface
{
    /**
     * Sets the expected input type (e.g. string, boolean)
     *
     * @param string $typeIndicator
     * @return FilterInterface
     */
    public function setType(string $typeIndicator): FilterInterface;

    /**
     * Set the default value (not applicable to booleans)
     *
     * @param $value
     * @return FilterInterface
     */
    public function setDefault($value): FilterInterface;

    /**
     * Add a callback to this filter (supports more than one)
     *
     * @param callable $func
     * @return FilterInterface
     */
    public function addCallback(callable $func): FilterInterface;

    /**
     * Process data using the filter rules.
     *
     * @param mixed $data
     * @return mixed
     */
    public function process($data);
}
