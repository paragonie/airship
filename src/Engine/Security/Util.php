<?php
declare(strict_types=1);
namespace Airship\Engine\Security;

abstract class Util
{
    const PRINTABLE_ASCII = "\x20\x21\x22\x23\x24\x25\x26\x27\x28\x29\x2a\x2b\x2c\x2d\x2e\x2f".
        "\x30\x31\x32\x33\x34\x35\x36\x37\x38\x39\x3a\x3b\x3c\x3d\x3e\x3f".
        "\x40\x41\x42\x43\x44\x45\x46\x47\x48\x49\x4a\x4b\x4c\x4d\x4e\x4f".
        "\x50\x51\x52\x53\x54\x55\x56\x57\x58\x59\x5a\x5b\x5c\x5d\x5e\x5f".
        "\x60\x61\x62\x63\x64\x65\x66\x67\x68\x69\x6a\x6b\x6c\x6d\x6e\x6f".
        "\x70\x71\x72\x73\x74\x75\x76\x77\x78\x79\x7a\x7b\x7c\x7d\x7e";
    const ALPHANUMERIC = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    const NUMERIC = '0123456789';
    const UPPER_ALPHA = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const LOWER_ALPHA = 'abcdefghijklmnopqrstuvwxyz';
    
    /**
     * Don't allow any HTML tags or attributes to be inserted into the DOM.
     * Prevents XSS attacks.
     * 
     * @param string $untrusted
     * @return string
     */
    public static function noHTML(string $untrusted) : string
    {
        return \htmlspecialchars(
            $untrusted,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
    }
    
    /**
     * Binary-safe substr() implementation
     *
     * @param string $str
     * @param int $start
     * @param int|null $length
     * @return string
     */
    public static function subString(string $str, int $start, $length = null) : string
    {
        if (\function_exists('\\mb_substr')) {
            return \mb_substr($str, $start, $length, '8bit');
        }
        return \substr($str, $start, $length);
    }

    /**
     * Binary-safe strlen() implementation
     *
     * @param string $str
     * @return string
     */
    public static function stringLength(string $str) : int
    {
        if (\function_exists('\\mb_substr')) {
            return \mb_strlen($str, '8bit');
        }
        return \strlen($str);
    }
    
    /**
     * Generate a random string of a given length and character set
     * 
     * @param int $length How many characters do you want?
     * @param string $characters Which characters to choose from
     * 
     * @return string
     * 
     * @throws Exception (via random_int())
     */
    public function randomString(int $len = 64, string $chars = self::PRINTABLE_ASCII) : string
    {
        $str = '';
        $l = self::stringLength($chars) - 1;
        for ($i = 0; $i < $len; ++$i) {
            $r = \random_int(0, $l);
            $str .= $chars[$r];
        }
        return $str;
    }
}
