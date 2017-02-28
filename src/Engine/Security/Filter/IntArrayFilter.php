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
                return parent::applyCallbacks($data, 0);
            } elseif (!\is_array($data)) {
                throw new \TypeError(
                    \sprintf('Expected an array of integers (%s).', $this->index)
                );
            }
            if (!\is1DArray($data)) {
                throw new \TypeError(
                    \sprintf('Expected a 1-dimensional array (%s).', $this->index)
                );
            }
            $data = (array) $data;
            foreach ($data as $key => $val) {
                if (\is_array($val)) {
                    throw new \TypeError(
                        \sprintf('Expected an integer at index %s (%s).', $key, $this->index)
                    );
                }
                if (\is_int($val) || \is_float($val)) {
                    $data[$key] = (int) $val;
                } elseif (\is_null($val) || $val === '') {
                    $data[$key] = $this->default;
                } elseif (\is_string($val) && \preg_match('#^\-?[0-9]+$#', $val)) {
                    $data[$key] = (int) $val;
                } else {
                    throw new \TypeError(
                        \sprintf('Expected an integer at index %s (%s).', $key, $this->index)
                    );
                }
            }
            return parent::applyCallbacks($data, 0);
        }
        return parent::applyCallbacks($data, $offset);
    }
}
