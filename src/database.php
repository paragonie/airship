<?php
declare(strict_types=1);

use \Airship\Alerts\Database\DBException;
use \Airship\Engine\{
    Database,
    Gears
};

$dbgear = Gears::getName('Database');
$databases = \Airship\loadJSON(ROOT.'/config/databases.json');
$dbPool = [];

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
                $conf[1] = null;
                $conf[2] = null;
                $conf[3] = $dbConf['options'];
            }
            
            try {
                // Let's store them in the database
                $dbPool[$label][] = $dbgear::factory(...$conf);
            } catch (DBException $e) {
                echo 'Could not connect to database: ', $label, '<br />', "\n";
                echo $e->getMessage();
                exit;
            }
        }
    }
}

// Cache this array for universal usage
$state->database_connections = $dbPool;