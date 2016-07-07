<?php
declare(strict_types=1);
namespace Airship\Engine\Security\Filter;

/**
 * Class FloatArrayFilter
 * @package Airship\Engine\Security\Filter
 */
class FloatArrayFilter extends ArrayFilter
{
    /**
     * @var float
     */
    protected $default = 0.0;

    /**
     * @var string
     */
    protected $type = 'float[]';

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
            if (!\is_array($data)) {
                throw new \TypeError('Expected an array of floats.');
            }
            if (!\is1DArray($data)) {
                throw new \TypeError('Expected a 1-dimensional array.');
            }
            foreach ($data as $key => $val) {
                if (\is_int($val) || \is_float($val)) {
                    $data[$key] = (float) $val;
                } elseif (\is_null($val) || $val === '') {
                    $data[$key] = (float) $this->default;
                } elseif (\is_string($val) && \is_numeric($val)) {
                    $data[$key] = (float) $val;
                } else {
                    throw new \TypeError(
                        \sprintf('Expected a float at index %s.', $key)
                    );
                }
            }
            return parent::applyCallbacks($data, 0);
        }
        return parent::applyCallbacks($data, $offset);
    }
}
