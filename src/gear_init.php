<?php
declare(strict_types=1);
// Initialize the base types for our framework gears
\Airship\Engine\Gears::init([
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

    'KeyUpdater' =>
        '\\Airship\\Engine\\Continuum\\Keygdrassil',
    
    'Landing' =>
        '\\Airship\\Engine\\Landing',
    
    'Ledger' =>
        '\\Airship\\Engine\\Ledger',
    
    'Lens' =>
        '\\Airship\\Engine\\Lens',
    
    'Permissions' =>
        '\\Airship\\Engine\\Security\\Permissions',
    
    'Translation' =>
        '\\Airship\\Engine\\Translation'
]);
