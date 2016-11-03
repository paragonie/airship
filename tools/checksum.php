<?php
declare(strict_types=1);

require_once \dirname(__DIR__).'/vendor/autoload.php';

/**
 * Generate a BLAKE2b-512 checksum of a given file.
 */

if (isset($argv[1])) {
    echo \ParagonIE\Halite\File::checksum($argv[1]), "\n";
}
