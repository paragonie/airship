<?php
declare(strict_types=1);
use \Airship\Engine\AutoPilot;
use \ParagonIE\CSPBuilder\CSPBuilder;

$cspCacheFile = ROOT . DIRECTORY_SEPARATOR .
    'tmp' . DIRECTORY_SEPARATOR .
    'cache' . DIRECTORY_SEPARATOR .
    'csp.' . AutoPilot::$active_cabin . '.json';

if (\file_exists($cspCacheFile) && false) {
    $csp = CSPBuilder::fromFile($cspCacheFile);
    $state->CSP = $csp;
} else {
    $cspfile = ROOT . DIRECTORY_SEPARATOR .
        'config' . DIRECTORY_SEPARATOR .
        'Cabin' . DIRECTORY_SEPARATOR .
        AutoPilot::$active_cabin . DIRECTORY_SEPARATOR .
        'content_security_policy.json';
    if (\file_exists($cspfile)) {
        $cabinPolicy = \Airship\loadJSON($cspfile);

        // Merge the cabin-specific policy with the base policy
        if (!empty($cabinPolicy['inherit'])) {
            $basePolicy = \Airship\loadJSON(
                ROOT . DIRECTORY_SEPARATOR .
                'config' . DIRECTORY_SEPARATOR .
                'content_security_policy.json'
            );
            $cabinPolicy = \Airship\csp_merge($cabinPolicy, $basePolicy);
        }
        \file_put_contents(
            $cspCacheFile,
            \json_encode($cabinPolicy, JSON_PRETTY_PRINT)
        );
        $csp = CSPBuilder::fromFile($cspCacheFile);
        $state->CSP = $csp;
    } else {
        // No cabin policy, use the default
        $csp = CSPBuilder::fromFile(
            ROOT . DIRECTORY_SEPARATOR .
            'config' . DIRECTORY_SEPARATOR .
            'content_security_policy.json'
        );
        $state->CSP = $csp;
    }
}
