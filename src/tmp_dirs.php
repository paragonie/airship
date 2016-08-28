<?php
declare(strict_types=1);

$tmpDirsClosure = function () {
    if (!\is_dir(ROOT . '/tmp/cache/' . $d)) {

    }
// Sanity check:
    $tmpDirs = [
        'comments',
        'csp_hash',
        'csp_static',
        'hash',
        'markdown',
        'static',
        'twig'
    ];
    foreach ($tmpDirs as $d) {
        if (!\is_dir(ROOT . '/tmp/cache/' . $d)) {
            \mkdir(
                ROOT . '/tmp/cache/' . $d,
                0775,
                true
            );
        }
    }
};
$tmpDirsClosure();
unset($tmpDirsClosure);
