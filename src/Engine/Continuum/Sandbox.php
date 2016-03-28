<?php
declare(strict_types=1);
namespace Airship\Engine\Continuum;

abstract class Sandbox
{
    /**
     * Require a file from within a Phar
     * 
     * @param string $file
     * @param array $previous_metadata
     * @return type
     */
    public static function safeRequire(string $file, array $previous_metadata = [])
    {
        return (require $file) === 1;
    }

    /**
     * Run a SQL file
     *
     * @param string $file
     * @param string $type
     * @return array
     * @throws \Airship\Alerts\Database\DBException
     */
    public static function runSQLFile(string $file, string $type)
    {
        $db = \Airship\get_database();
        if ($db->getDriver() !== $type) {
            // Wrong type. Abort!
            return;
        }
        return $db->safeQuery(\file_get_contents($file));
    }
}
