<?php
declare(strict_types=1);

use Airship\Engine\{
    Cache\File as FileCache,
    Cache\SharedMemory as MemoryCache
};
use ParagonIE\ConstantTime\Binary;

if (empty($_POST)) {
    /**
     * Let's get rid of trailing slashes in URLs without POST data
     */
    $sliceAt = Binary::safeStrlen($_SERVER['REQUEST_URI']) - 1;
    if ($sliceAt > 0 && $_SERVER['REQUEST_URI'][$sliceAt] === '/') {
        \Airship\redirect(
            '/' . \trim($_SERVER['REQUEST_URI'], '/')
        );
    }

    /**
     * Let's handle static content caching
     */
    if (\extension_loaded('apcu')) {
        $staticCache = (new MemoryCache())
            ->personalize('staticPage:');
        $cspCache = (new MemoryCache())
            ->personalize('contentSecurityPolicy:');
    } else {
        if (!\is_dir(ROOT . '/tmp/cache/static')) {
            require_once ROOT . '/tmp_dirs.php';
        }
        $staticCache = new FileCache(ROOT . '/tmp/cache/static');
        $cspCache = new FileCache(ROOT . '/tmp/cache/csp_static');
    }
    $port = $_SERVER['HTTP_PORT'] ?? '';
    $lookup = $_SERVER['HTTP_HOST'] . ':' . $port . '/' . $_SERVER['REQUEST_URI'];
    $staticPage = $staticCache->get($lookup);
    if (!empty($staticPage)) {
        if (!\headers_sent()) {
            foreach (\Airship\get_standard_headers('text/plain;charset=UTF-8') as $left => $right) {
                \header($left . ': ' . $right);
            }
        }
        $csp =  $cspCache->get($lookup);
        if (!empty($csp)) {
            foreach (\json_decode($csp, true) as $cspHeader) {
                \header($cspHeader);
            }
        }

        echo $staticPage;
        // This is just for benchmarking purposes:
        echo '<!-- Load time: ' . \round(\microtime(true) - $start, 5) . ' s (static page) -->';
        exit;
    }
    unset($staticCache);
}
