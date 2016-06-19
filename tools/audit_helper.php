<?php
/**
 * Generates a list of all files -- use this for narrowing
 * the scope of a pre-release code audit.
 */
require_once \dirname(__DIR__).'/src/bootstrap.php';

if ($argc > 1) {
    $extensions = \array_slice($argv, 1);
} else {
    $extensions = ['php', 'twig'];
}
$fileList = [];
$repository = 'https://github.com/paragonie/airship/blob/master/';
$cutoff = \strlen(\dirname(__DIR__) . '/src') + 1;
$dirs = [];

$allDirs = \Airship\list_all_files(\dirname(__DIR__) . '/src');
\sort($allDirs);
foreach ($allDirs as $file) {
    $print = \trim(\substr($file, $cutoff), '/');

    $pieces = \explode('/', $print);
    $max = \count($pieces) - 1;
    $name = \array_pop($pieces);

    if (\substr($print, 0, 3) === 'tmp' || \substr($print, 0, 5) === 'files') {
        continue;
    }

    $currentDir = \implode('/', $pieces);
    if ($max > 0 && !isset($dirs[$currentDir])) {
        echo \str_repeat(' ', 3 * ($max - 1)) . '- [ ] ';
        echo $pieces[$max - 1];
        $dirs[$currentDir] = true;
        echo "\n";
    }
    if (\preg_match('/\.([^.]+)$/', $name, $m)) {
        if (!\in_array($m[1], $extensions)) {
            continue;
        }
    } else {
        continue;
    }

    echo \str_repeat(' ', 3 * $max) . '- [ ] ';
    echo '[' . $name . '](' . $repository . $print . ')';
    echo "\n";
}
