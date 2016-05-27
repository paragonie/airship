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
     * Use HKDF to derive multiple keys from one.
     * http://tools.ietf.org/html/rfc5869
     *
     * @param string $hash Hash Function
     * @param string $ikm Initial Keying Material
     * @param int $length How many bytes?
     * @param string $info What sort of key are we deriving?
     * @param string $salt
     * @return string
     * @throws \Exception
     * @throws \InvalidArgumentException
     */
    function hash_hkdf(
        string $hash,
        string $ikm,
        int $length, 
        string $info = '', 
        string $salt = null
    ): string {
        if (\strtolower($hash) === 'blake2b') {
            // Punt to Halite.
            if ($salt) {
                return Util::hkdfBlake2b($ikm, $length, $info, $salt);
            }
            return Util::hkdfBlake2b($ikm, $length, $info);
        }
        // Find the correct digest length
        $digest_length = Util::safeStrlen(
            \hash_hmac($hash, '', '', true)
        );

        // Sanity-check the desired output length.
        if (empty($length) || !\is_int($length) ||
            $length < 0 || $length > 255 * $digest_length) {
            throw new \InvalidArgumentException(
                \trk("errors.crypto.hkdf_bad_digest_length")
            );
        }
        // "if [salt] not provided, is set to a string of HashLen zeroes."
        if (\is_null($salt)) {
            $salt = \str_repeat("\x00", $digest_length);
        }

        // HKDF-Extract:
        // PRK = HMAC-Hash(salt, IKM)
        // The salt is the HMAC key.
        $prk = \hash_hmac($hash, $ikm, $salt, true);

        // HKDF-Expand:
        // This check is useless, but it serves as a reminder to the spec.
        if (mb_strlen($prk, '8bit') < $digest_length) {
            throw new \Exception('HKDF-Expand failed');
        }
        // T(0) = ''
        $t = '';
        $last_block = '';
        for ($block_index = 1; Util::safeStrlen($t) < $length; ++$block_index) {
            // T(i) = HMAC-Hash(PRK, T(i-1) | info | 0x??)
            $last_block = \hash_hmac(
                $hash,
                $last_block . $info . \chr($block_index),
                $prk,
                true
            );
            // T = T(1) | T(2) | T(3) | ... | T(N)
            $t .= $last_block;
        }
        // ORM = first L octets of T
        $orm = Util::safeSubstr($t, 0, $length);
        if ($orm === FALSE) {
            throw new \Exception(
                \trk('errors.crypto.general_error')
            );
        }
        return $orm;
    }

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
     * Returns true if every member of an array is NOT another array
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
     * Generate a UUID following the version 4 specification
     *
     * @return string
     */
    function UUIDv4()
    {
        return \implode('-', [
            \Sodium\bin2hex(random_bytes(4)),
            \Sodium\bin2hex(random_bytes(2)),
            \Sodium\bin2hex(
                \pack(
                    'C',
                    (ord(\random_bytes(1)) & 0x0F) | 0x40
                )
            ) . \Sodium\bin2hex(\random_bytes(1)),
            \Sodium\bin2hex(
                \pack(
                    'C',
                    (\ord(\random_bytes(1)) & 0x3F) | 0x80
                )
            ) . \Sodium\bin2hex(\random_bytes(1)),
            \Sodium\bin2hex(\random_bytes(6))
        ]);
    }

/**
 * II. TRANSLATION FUNCTIONS
 */
    if (!function_exists('__')) {
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
                $state->lang ?? 'en-us',
                ...$params
            );
        }
    }
