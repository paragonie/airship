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
        '\\Airship\\Engine\\Continuum',
    
    'Authentication' =>
        '\\Airship\\Engine\\Security\\Authentication',
    
    'Model' =>
        '\\Airship\\Engine\\Model',
    
    'Database' =>
        '\\Airship\\Engine\\Database',
    
    'CSRF' =>
        '\\Airship\\Engine\\Security\\CSRF',
    
    'Hail' =>
        '\\Airship\\Engine\\Hail',

    'HTTPResponse' =>
        '\\Airship\\Engine\\Networking\\HTTP\\Response',
    
    'Controller' =>
        '\\Airship\\Engine\\Controller',
    
    'Ledger' =>
        '\\Airship\\Engine\\Ledger',
    
    'View' =>
        '\\Airship\\Engine\\View',
    
    'Permissions' =>
        '\\Airship\\Engine\\Security\\Permissions',

    'ServerRequest' =>
        '\\Airship\\Engine\\Networking\\HTTP\\ServerRequest',
    
    'Translation' =>
        '\\Airship\\Engine\\Translation',
    
    'TreeUpdater' =>
        '\\Airship\\Engine\\Keyggdrasil'
]);
