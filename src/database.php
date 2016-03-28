<?php
declare(strict_types=1);
$dbgear = \Airship\Engine\Gears::getName('Database');
$databases = \Airship\loadJSON(ROOT.'/config/databases.json');
foreach ($databases as $label => $dbs) {
    $db[$label] = [];
    foreach ($dbs as $dbconf) {
        if (isset($dbconf['driver']) || isset($dbconf['dsn'])) {
            $conf = [
                isset($dbconf['dsn'])
                    ? $dbconf['dsn']
                    : $dbconf
            ];
            
            if (isset($dbconf['username']) && isset($dbconf['password'])) {
                $conf[] = $dbconf['username'];
                $conf[] = $dbconf['password'];
                if (isset($dbconf['options'])) {
                    $conf[] = $dbconf['options'];
                }
            } elseif (isset($dbconf['options'])) {
                $conf[1] = null;
                $conf[2] = null;
                $conf[3] = $dbconf['options'];
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