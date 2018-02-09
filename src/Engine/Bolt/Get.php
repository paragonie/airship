<?php
declare(strict_types=1);
namespace Airship\Engine\Bolt;

use ParagonIE\ConstantTime\Binary;

/**
 * Trait Get
 *
 * Handle HTTP get params on mildly misconfigured servers.
 *
 * @package Airship\Engine\Bolt
 */
trait Get
{
    /**
     * Work around poorly-configured web servers by parsing out the GET parameters
     *
     * Be forewarned: this will overwrite $lastPiece
     *
     * @param string& $lastPiece (optional)
     * @return array
     * @throws \TypeError
     */
    protected function httpGetParams(string &$lastPiece = null): array
    {
        if ($lastPiece === null) {
            return $_GET ?? [];
        }
        $p = \strpos($lastPiece, '?');
        if ($p !== false && empty($_GET)) {
            $_GET = \Airship\array_from_http_query(
                Binary::safeSubstr($lastPiece, $p + 1)
            );
            $lastPiece = Binary::safeSubstr($lastPiece, 0, $p);
        }
        return $_GET;
    }
}