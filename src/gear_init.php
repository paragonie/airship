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
    
    'Blueprint' =>
        '\\Airship\\Engine\\Blueprint',
    
    'Database' =>
        '\\Airship\\Engine\\Database',
    
    'CSRF' =>
        '\\Airship\\Engine\\Security\\CSRF',
    
    'Hail' =>
        '\\Airship\\Engine\\Hail',

    'HTTPResponse' =>
        '\\Airship\\Engine\\Networking\\HTTP\\Response',
    
    'Landing' =>
        '\\Airship\\Engine\\Landing',
    
    'Ledger' =>
        '\\Airship\\Engine\\Ledger',
    
    'Lens' =>
        '\\Airship\\Engine\\Lens',
    
    'Permissions' =>
        '\\Airship\\Engine\\Security\\Permissions',

    'ServerRequest' =>
        '\\Airship\\Engine\\Networking\\HTTP\\ServerRequest',
    
    'Translation' =>
        '\\Airship\\Engine\\Translation',
    
    'TreeUpdater' =>
        '\\Airship\\Engine\\Keyggdrasil'
]);
