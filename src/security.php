<?php
declare(strict_types=1);

use \Airship\Engine\{
    AutoPilot,
    State
};
use \ParagonIE\CSPBuilder\CSPBuilder;

/**
 * @global State $state
 */

$cspCacheFile = ROOT . '/tmp/cache/csp.' . AutoPilot::$active_cabin . '.json';

if (\file_exists($cspCacheFile) && false) {
    $csp = CSPBuilder::fromFile($cspCacheFile);
    $state->CSP = $csp;
} else {
    $cspfile = ROOT . '/config/Cabin/' . AutoPilot::$active_cabin . '/content_security_policy.json';
    if (\file_exists($cspfile)) {
        $cabinPolicy = \Airship\loadJSON($cspfile);

        // Merge the cabin-specific policy with the base policy
        if (!empty($cabinPolicy['inherit'])) {
            $basePolicy = \Airship\loadJSON(ROOT . '/config/content_security_policy.json');
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
        $csp = CSPBuilder::fromFile(ROOT . '/config/content_security_policy.json');
        $state->CSP = $csp;
    }
}
