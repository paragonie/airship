<?php
declare(strict_types=1);
namespace Airship\Engine\Security\Filter;

/**
 * Class IntArrayFilter
 * @package Airship\Engine\Security\Filter
 */
class IntArrayFilter extends ArrayFilter
{
    /**
     * @var int
     */
    protected $default = 0;

    /**
     * @var string
     */
    protected $type = 'int[]';

    /**
     * Apply all of the callbacks for this filter.
     *
     * @param mixed $data
     * @param int $offset
     * @return mixed
     * @throws \TypeError
     */
    public function applyCallbacks($data = null, int $offset = 0)
    {
        if ($offset === 0) {
            if (\is_null($data)) {
                $data = [];
            } elseif (!\is_array($data)) {
                throw new \TypeError('Expected an array of integers.');
            }
            if (!\is1DArray($data)) {
                throw new \TypeError('Expected a 1-dimensional array.');
            }
            foreach ($data as $key => $val) {
                if (\is_int($val) || \is_float($val)) {
                    $data[$key] = (int) $val;
                } elseif (\is_null($val) || $val === '') {
                    $data[$key] = $this->default;
                } elseif (\is_string($data) && \preg_match('#^\-?[0-9]+$#', $data)) {
                    $data[$key] = (int) $data;
                } else {
                    throw new \TypeError(
                        \sprintf('Expected an integer at index %s.', $key)
                    );
                }
            }
            return parent::applyCallbacks($data, 0);
        }
        return parent::applyCallbacks($data, $offset);
    }
}
