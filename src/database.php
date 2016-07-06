<?php
declare(strict_types=1);

use \Airship\Alerts\Database\DBException;
use \Airship\Engine\{
    Database,
    Gears,
    State
};

/**
 * Set up the Database objects from our database.json file
 *
 * @global State $state
 */

$dbgear = Gears::getName('Database');
$databases = \Airship\loadJSON(
    ROOT . '/config/databases.json'
);
$dbPool = [];

// Needed for IDE code completion:
if (IDE_HACKS) {
    $dbgear = new Database(new \PDO('sqlite::memory:'));
}

/**
 * Initialize all of our database connections:
 */
foreach ($databases as $label => $dbs) {
    $dbPool[$label] = [];
    foreach ($dbs as $dbConf) {
        if (isset($dbConf['driver']) || isset($dbConf['dsn'])) {
            $conf = [
                isset($dbConf['dsn'])
                    ? $dbConf['dsn']
                    : $dbConf
            ];
            
            if (isset($dbConf['username']) && isset($dbConf['password'])) {
                $conf[] = $dbConf['username'];
                $conf[] = $dbConf['password'];
                if (isset($dbConf['options'])) {
                    $conf[] = $dbConf['options'];
                }
            } elseif (isset($dbConf['options'])) {
                $conf[1] = '';
                $conf[2] = '';
                $conf[3] = $dbConf['options'];
            }
            $dbPool[$label][] = $conf;
        }
    }
}

// Cache this array for universal usage
$state->database_connections = $dbPool;

try {
    \Airship\get_database();
} catch (DBException $e) {
    echo 'Could not connect to database. ', '<br />', "\n";
    echo $e->getMessage();
    exit;
}
