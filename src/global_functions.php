<?php
declare(strict_types=1);

use \ParagonIE\Halite\Util;
use \Airship\Engine\{
    Gears,
    State
};
/**
 * These functions are defined in the global scope.
 */

/**
 * I. GENERAL FUNCTIONS
 */
    /**
     * Returns true if every member of an array is NOT another array
     *
     * @param array $source
     * @return bool
     */
    function is1DArray(array $source): bool
    {
        return \count($source) === \count($source, \COUNT_RECURSIVE);
    }

    /**
     * Returns true if every member of an array is a 1-dimensional array.
     *
     * @param array $source
     * @param bool $allow1D Permit non-array children?
     * @param bool $constantTime Don't exit early
     * @return bool
     */
    function is2DArray(array $source, bool $allow1D = false, bool $constantTime = false): bool
    {
        $ret = !empty($source);
        foreach ($source as $row) {
            if (!\is_array($row)) {
                if ($allow1D) {
                    continue;
                }
                if (!$constantTime) {
                    return false;
                }
                $ret = false;
            }
            if (!\is1DArray($row)) {
                if (!$constantTime) {
                    return false;
                }
                $ret = false;
            }
        }
        return $ret;
    }

/**
 * II. TRANSLATION FUNCTIONS
 */
    if (!function_exists('__')) {
        /**
         * Translate this string.
         *
         * @param string $text
         * @param string $domain
         * @param array ...$params
         * @return string
         */
        function __(string $text, string $domain = 'default', ...$params)
        {
            static $gear = null;
            if ($gear === null) {
                $gear = Gears::get('Translation');
            }
            return \sprintf(
                $gear->literal($text, $domain),
                ...$params
            );
        }
    }

    if (!function_exists('_e')) {
        /**
         * Translate and echo a string of text
         * 
         * @param string $text String to translate
         * @param mixed ...$params
         * @return string
         */
        function _e(string $text, ...$params) : string
        {
            echo __($text, ...$params);
        }
    }

    if (!function_exists('_n')) {
        /**
         * Print a number (great for handling plurals)
         * 
         * @param string $text Text for a singular value
         * @param string $pltext Text for a plural value
         * @param int $arg The argument that decides which string
         * @param mixed ...$params
         * @return string
         */
        function _n(string $text, string $pltext, int $arg, ...$params) : string
        {
            if (abs($arg) == 1) {
                return __($text, ...$params);
            } else {
                return __($pltext, ...$params);
            }
        }
    }

    if (!function_exists('trk')) {
        /**
         * Translation (lookup table based on a key)
         * 
         * @param string $key
         * @param mixed ...$params
         * @return string
         */
        function trk(string $key, ...$params): string
        {
            static $gear = null;
            if ($gear === null) {
                $gear = Gears::get('Translation');
            }
            $state = State::instance();
            return $gear->lookup(
                $key,
                (string) ($state->lang ?? 'en-us'),
                ...$params
            );
        }
    }
