<?php
declare(strict_types=1);

use \Airship\Engine\{
    AutoPilot,
    State
};
use \ParagonIE\CSPBuilder\CSPBuilder;
use \ParagonIE\HPKPBuilder\HPKPBuilder;

/**
 * Security enhancements (i.e. CSP, HPKP) are defined here.
 *
 * @global State $state
 */

/**
 * First, Content-Security-Policy headers:
 */
$cspCacheFile = ROOT . '/tmp/cache/csp.' . AutoPilot::$active_cabin . '.json';
if (\file_exists($cspCacheFile) && \filesize($cspCacheFile) > 0) {
    $csp = CSPBuilder::fromFile($cspCacheFile);
} else {
    $cspfile = ROOT . '/config/Cabin/' . AutoPilot::$active_cabin . '/content_security_policy.json';
    if (\file_exists($cspfile)) {
        $cabinPolicy = \Airship\loadJSON($cspfile);

        // Merge the cabin-specific policy with the base policy
        if (!empty($cabinPolicy['inherit'])) {
            $basePolicy = \Airship\loadJSON(
                ROOT . '/config/content_security_policy.json'
            );
            $cabinPolicy = \Airship\csp_merge(
                $cabinPolicy,
                $basePolicy
            );
        }
        \Airship\saveJSON(
            $cspCacheFile,
            $cabinPolicy
        );
        $csp = CSPBuilder::fromFile($cspCacheFile);
    } else {
        // No cabin policy, use the default
        $csp = CSPBuilder::fromFile(ROOT . '/config/content_security_policy.json');
    }
}
$state->CSP = $csp;

/**
 * Next, if we're connected over HTTPS, send an HPKP header too:
 */
if (AutoPilot::isHTTPSConnection()) {
    $hpkpCacheFile = ROOT . '/tmp/cache/hpkp.' . AutoPilot::$active_cabin . '.json';
    if (\file_exists($hpkpCacheFile) && \filesize($hpkpCacheFile) > 0) {
        $hpkp = HPKPBuilder::fromFile($hpkpCacheFile);
        $state->HPKP = $hpkp;
    } else {
        $hpkpConfig = $state->cabins[AutoPilot::$cabinIndex]['hpkp'];
                
        if ($hpkpConfig['enabled'] && \count($hpkpConfig['hashes']) > 1) {
            $hpkp = (new HPKPBuilder())
                ->includeSubdomains($hpkpConfig['include-subdomains'])
                ->maxAge($hpkpConfig['max-age'])
                ->reportOnly($hpkpConfig['report-only'])
                ->reportUri($hpkpConfig['report-uri']);
            foreach ($hpkpConfig['hashes'] as $h) {
                $hpkp->addHash($h['hash'], $h['algo'] ?? 'sha256');
            }
            \file_put_contents(
                $hpkpCacheFile,
                $hpkp->getJSON()
            );
            $state->HPKP = $hpkp;
        } else {
            $state->HPKP = null;
        }
    }
}
