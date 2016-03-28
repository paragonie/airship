<?php
declare(strict_types=1);
require_once \dirname(__DIR__).'/vendor/autoload.php';

if (isset($argv[1])) {
    echo \ParagonIE\Halite\File::checksum($argv[1]), "\n";
}
