<?php
declare(strict_types=1);
namespace Airship\Engine\Security\Filter;

/**
 * Class StringArrayFilter
 * @package Airship\Engine\Security\Filter
 */
class StringArrayFilter extends ArrayFilter
{
    /**
     * @var string
     */
    protected $type = 'string[]';

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
                throw new \TypeError('Expected an array of string (%s).', $this->index);
            }
            if (!\is1DArray($data)) {
                throw new \TypeError('Expected a 1-dimensional array (%s).', $this->index);
            }
            foreach ($data as $key => $val) {
                if (\is_null($val)) {
                    $data[$key] = '';
                } elseif (\is_numeric($val)) {
                    $data[$key] = (string) $val;
                } elseif (!\is_string($val)) {
                    throw new \TypeError(
                        \sprintf('Expected a string at index %s (%s).', $key, $this->index)
                    );
                }
            }
            return parent::applyCallbacks($data, 0);
        }
        return parent::applyCallbacks($data, $offset);
    }
}
