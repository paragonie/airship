<?php
declare(strict_types=1);
namespace Airship\Engine\Cache;

use Airship\Engine\State;
use Airship\Engine\Security\Util;
use Gregwar\RST\Parser as RSTParser;
use League\CommonMark\CommonMarkConverter;
use ParagonIE\ConstantTime\Binary;
use ParagonIE\Halite\Alerts\CannotPerformOperation;
use ParagonIE\Halite\Util as CryptoUtil;

/**
 * Class ViewCache
 * @package Airship\Engine\Cache
 */
class ViewCache
{
    /**
     * @var \HTMLPurifier
     */
    public static $htmlPurifier = null;

    /**
     * @var CommonMarkConverter
     */
    public static $md = null;

    /**
     * @var RSTParser
     */
    public static $rst = null;

    /**
     * Render (and cache) markdown into HTML
     *
     * @param string $data
     * @param bool $return
     *
     * @return string
     *
     * @throws \Error
     * @throws CannotPerformOperation
     * @throws \TypeError
     */
    public static function markdown(string $data, bool $return = false): string
    {
        if (empty(self::$md)) {
            self::$md = new CommonMarkConverter();
        }

        $checksum = CryptoUtil::hash('Markdown' . $data);

        $output = static::generic($checksum, 'markdown', self::$md, 'convertToHtml', $data);
        if ($return) {
            return (string) $output;
        }
        echo $output;
        return '';
    }

    /**
     * Render (and cache) purified HTML
     *
     * @param string $data
     * @return string
     * @throws CannotPerformOperation
     * @throws \Error
     * @throws \TypeError
     */
    public static function purify(string $data): string
    {
        if (!self::$htmlPurifier) {
            $state = State::instance();
            /**
             * @var \HTMLPurifier
             */
            self::$htmlPurifier = $state->HTMLPurifier;
        }
        $checksum = CryptoUtil::hash('HTML Purifier' . $data);

        $output = static::generic(
            $checksum,
            'html_purifier',
            self::$htmlPurifier,
            'purify',
            $data
        );
        return (string) $output;

    }

    /**
     * Render (and cache) ReStructuredText into HTML.
     *
     * @param string $data
     * @param bool $return
     *
     * @return string
     * @throws CannotPerformOperation
     * @throws \Error
     * @throws \TypeError
     */
    public static function rst(string $data, bool $return = false): string
    {
        if (empty(self::$rst)) {
            self::$rst = (new RSTParser())
                ->setIncludePolicy(false);
        }

        $checksum = CryptoUtil::hash('ReStructuredText' . $data);
        $output = static::generic(
            $checksum,
            'rst',
            self::$rst,
            'parse',
            $data
        );

        if ($return) {
            return (string) $output;
        }
        echo $output;
        return '';
    }

    /**
     * Generic caching function.
     *
     * @param string $checksum Input checksum
     * @param string $type     Type (subdirectory name)
     * @param mixed  $object   An object
     * @param string $method   A method on the objcet
     * @param string $data
     *
     * @return string
     * @throws \Error
     */
    protected static function generic(
        string $checksum,
        string $type,
        $object,
        string $method,
        string $data = ''
    ): string {
        $h1 = Binary::safeSubstr($checksum, 0, 2);
        $h2 = Binary::safeSubstr($checksum, 2, 2);
        $hash = Binary::safeSubstr($checksum, 4);

        $cacheDir = \implode(
            '/',
            [
                ROOT,
                'tmp',
                'cache',
                self::escapeDirName($type),
                $h1,
                $h2
            ]
        );

        if (\file_exists($cacheDir . '/' . $hash . '.txt')) {
            $output = \file_get_contents(
                $cacheDir . '/' . $hash . '.txt'
            );
        } else {
            if (!\is_dir($cacheDir)) {
                \mkdir($cacheDir, 0775, true);
            }
            if (!\method_exists($object, $method)) {
                throw new \Error(
                    \sprintf('Unknown method %s on class %s', $method, \get_class($object))
                );
            }
            $output = $object->$method($data);
            // Cache for later
            \file_put_contents(
                $cacheDir . '/' . $hash . '.txt',
                $output
            );
            \chmod(
                $cacheDir . '/' . $hash . '.txt',
                0664
            );
        }
        return $output;
    }

    /**
     * @param string $domainSeparator
     * @param string $subdir
     * @param string $hashData
     *
     * @return array<int, string>
     * @throws CannotPerformOperation
     * @throws \Error
     * @throws \TypeError
     */
    public static function getFile(string $domainSeparator, string $subdir, string $hashData = ''): array
    {
        $checksum = CryptoUtil::hash($domainSeparator . $hashData);

        $h1 = Binary::safeSubstr($checksum, 0, 2);
        $h2 = Binary::safeSubstr($checksum, 2, 2);
        $hash = Binary::safeSubstr($checksum, 4);

        return [
            \implode(
                '/',
                [
                    ROOT,
                    'tmp',
                    'cache',
                    $subdir,
                    $h1,
                    $h2
                ]
            ),
            $hash . '.txt'
        ];
    }

    /**
     * Prevent LFI and path traversal vulnerabilities by only allowing a strict
     * whitelist.
     *
     * @param string $in
     *
    * @return string
     */
    protected static function escapeDirName(string $in): string
    {
        return Util::charWhitelist($in, Util::BASE64_URLSAFE);
    }
}
