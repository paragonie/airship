<?php
declare(strict_types=1);
namespace Airship\Engine\Security\Filter;

/**
 * Class BoolArrayFilter
 * @package Airship\Engine\Security\Filter
 */
class BoolArrayFilter extends ArrayFilter
{
    /**
     * @var string
     */
    protected $type = 'bool[]';

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
                throw new \TypeError('Expected an array of booleans.');
            }
            if (!\is1DArray($data)) {
                throw new \TypeError('Expected a 1-dimensional array.');
            }
            foreach ($data as $key => $val) {
                if (\is_null($data) || $data === '') {
                    $data[$key] = null;
                } else {
                    $data[$key] = !empty($val);
                }
            }
            return parent::applyCallbacks($data, 0);
        }
        return parent::applyCallbacks($data, $offset);
    }
}
