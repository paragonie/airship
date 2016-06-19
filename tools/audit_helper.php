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

foreach ($extensions as $ex) {
    foreach (\Airship\list_all_files(\dirname(__DIR__) . '/src', $ex) as $file) {
        $print = \trim(\substr($file, $cutoff), '/');

        $pieces = \explode('/', $print);
        $max = \count($pieces) - 1;
        $name = \array_pop($pieces);
        $currentDir = \implode('/', $pieces);
        if ($max > 0 && !isset($dirs[$currentDir])) {
            echo \str_repeat(' ', 4 * ($max - 1)) . '- [ ] ';
            echo $pieces[$max - 1];
            $dirs[$currentDir] = true;
            echo "\n";
        }

        echo \str_repeat(' ', 4 * $max) . '- [ ] ';
        echo '[' . $name . '](' . $repository . $print . ')';
        echo "\n";
    }
}
