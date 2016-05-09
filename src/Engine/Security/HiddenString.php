<?php
declare(strict_types=1);
namespace Airship\Engine\Security;

use ParagonIE\Halite\Util;

/**
 * Class HiddenString
 *
 * This is just to hide sensitive strings from stack traces, etc.
 *
 * @package Airship\Engine\Security
 */
class HiddenString
{
    protected $internalStringValue = '';
    protected $allowInline = false;

    public function __construct(string $value, bool $allowInline = false)
    {
        $this->internalStringValue = Util::safeStrcpy($value);
        $this->allowInline = $allowInline;
    }

    /**
     * Hide its internal state from var_dump()
     *
     * @return array
     */
    public function __debugInfo()
    {
        return [
            'interalStringValue' =>
                '*',
            'attention' =>
                'If you need the value of a HiddenString, invoke getString() instead of dumping it.'
        ];
    }

    /**
     * Wipe it from memory after it's been used.
     */
    public function __destruct()
    {
        \Sodium\memzero($this->internalStringValue);
    }

    /**
     * Explicit invocation -- get the raw string value
     *
     * @return string
     */
    public function getString(): string
    {
        return Util::safeStrcpy($this->internalStringValue);
    }

    /**
     * Prevent accidental echoing of a hidden string
     *
     * @return string
     */
    public function __toString(): string
    {
        if ($this->allowInline) {
            return Util::safeStrcpy($this->internalStringValue);
        }
        return '';
    }
}
