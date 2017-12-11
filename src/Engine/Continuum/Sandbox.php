<?php
declare(strict_types=1);
namespace Airship\Engine\Continuum;

/**
 * Class Sandbox
 * @package Airship\Engine\Continuum
 */
abstract class Sandbox
{
    /**
     * Require a file from within a Phar
     *
     * @param string $file
     * @param array $previous_metadata
     * @return bool
     * @psalm-suppress UnresolvableInclude
     */
    public static function safeRequire(
        string $file,
        /** @noinspection PhpUnusedParameterInspection */
        array $previous_metadata = []
    ): bool {
        return (require $file) === 1;
    }

    /**
     * Include a file from within a Phar
     *
     * @param string $file
     * @param array $previous_metadata
     * @return bool
     * @psalm-suppress UnresolvableInclude
     */
    public static function safeInclude(
        string $file,
        /** @noinspection PhpUnusedParameterInspection */
        array $previous_metadata = []
    ): bool {
        return (include $file) === 1;
    }

    /**
     * Run a SQL file
     *
     * @param string $file
     * @param string $type
     * @return array
     * @throws \Airship\Alerts\Database\DBException
     */
    public static function runSQLFile(string $file, string $type): array
    {
        $db = \Airship\get_database();
        if ($db->getDriver() !== $type) {
            // Wrong type. Abort!
            return [];
        }
        $contents = \file_get_contents($file);
        if (!\is_string($contents)) {
            // Wrong type. Abort!
            return [];
        }
        return $db->safeQuery($contents);
    }
}
