<?php
declare(strict_types=1);

use ParagonIE\ConstantTime\Binary;

require_once \dirname(__DIR__).'/src/bootstrap.php';

/**
 * Grabs a random file and tells you to audit it.
 */

if ($argc > 1) {
    $extensions = \array_slice($argv, 1);
} else {
    $extensions = ['php', 'twig'];
}
$fileList = [];
foreach ($extensions as $ex) {
    foreach (\Airship\list_all_files(\dirname(__DIR__) . '/src/', $ex) as $file) {
        $fileList []= $file;
    }
}

$choice = \random_int(0, \count($fileList) - 1);

echo "Audit this file:\n\t";

$l = Binary::safeStrlen(\dirname(__DIR__));

echo Binary::safeSubstr($fileList[$choice], $l), "\n";
