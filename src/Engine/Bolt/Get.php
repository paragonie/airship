<?php
declare(strict_types=1);
namespace Airship\Engine\Bolt;

trait Get
{
    /**
     * Work around poorly-configured web servers by parsing out the GET parameters
     *
     * Be forewarned: this will overwrite $lastPiece
     *
     * @param string& $lastPiece (optional)
     * @return array
     */
    protected function httpGetParams(string &$lastPiece = null): array
    {
        if ($lastPiece === null) {
            return $_GET ?? [];
        }
        $p = \strpos($lastPiece, '?');
        if ($p !== false && empty($_GET)) {
            $_GET = \Airship\array_from_http_query(
                \substr($lastPiece, $p + 1)
            );
            $lastPiece = \substr($lastPiece, 0, $p);
        }
        return $_GET;
    }
}