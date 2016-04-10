<?php
declare(strict_types=1);
$dbgear = \Airship\Engine\Gears::getName('Database');
$databases = \Airship\loadJSON(ROOT.'/config/databases.json');
foreach ($databases as $label => $dbs) {
    $db[$label] = [];
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
                $db[$label][] = $dbgear::factory(...$conf);
            } catch (\Exception $e) {
                echo 'Could not connect to database: ', $label, '<br />', "\n";
                echo $e->getMessage();
                exit;
            }
        }
    }
}
// Cache this array for universal usage
$state->database_connections = $db;