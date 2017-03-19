<?php
declare(strict_types=1);
/**
 * Initialize the base types for our framework gears
 */
\Airship\Engine\Gears::init([
    'AirBrake' =>
        '\\Airship\\Engine\\Security\\AirBrake',

    'AutoPilot' =>
        '\\Airship\\Engine\\AutoPilot',
    
    'AutoUpdater' =>
        [
            '\\Airship\\Engine\\Continuum',
            '\\Airship\\Engine\\Contract\\ContinuumInterface'
        ],
    
    'Authentication' =>
        '\\Airship\\Engine\\Security\\Authentication',

    'Controller' =>
        '\\Airship\\Engine\\Controller',

    'CSRF' =>
        '\\Airship\\Engine\\Security\\CSRF',
    
    'Database' =>
        [
            '\\Airship\\Engine\\Database',
            '\\Airship\\Engine\\Contract\\DBInterface'
        ],
    
    'Hail' =>
        '\\Airship\\Engine\\Hail',

    'HTTPResponse' =>
        '\\Airship\\Engine\\Networking\\HTTP\\Response',
    
    'Ledger' =>
        '\\Airship\\Engine\\Ledger',

    'Model' =>
        '\\Airship\\Engine\\Model',
    
    'Permissions' =>
        '\\Airship\\Engine\\Security\\Permissions',

    'ServerRequest' =>
        '\\Airship\\Engine\\Networking\\HTTP\\ServerRequest',
    
    'Translation' =>
        '\\Airship\\Engine\\Translation',
    
    'TreeUpdater' =>
        '\\Airship\\Engine\\Keyggdrasil',

    'View' =>
        '\\Airship\\Engine\\View',

    'ViewCache' =>
        '\\Airship\\Engine\\Cache\\ViewCache'

]);
