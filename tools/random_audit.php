<?php
/**
 * Grabs a random file and tells you to audit it.
 */
require_once \dirname(__DIR__).'/src/bootstrap.php';

$extensions = ['php', 'twig'];
$fileList = [];
foreach ($extensions as $ex) {
    foreach (\Airship\list_all_files(\dirname(__DIR__) . '/src/', $ex) as $file) {
        $fileList []= $file;
    }
}

$choice = \random_int(0, \count($fileList) - 1);

echo "Audit this file:\n\t";

$l = \strlen(\dirname(__DIR__));

echo \substr($fileList[$choice], $l), "\n";