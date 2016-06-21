<?php
declare(strict_types=1);

use \Airship\Engine\State;

/**
 * @global array $active
 * @global State $state
 *
 * Load this in index.php, or anywhere /AFTER/ the CABIN_DIR constant exists
 */
if (\defined('CABIN_DIR') && \file_exists(ROOT.'/tmp/cache/' . CABIN_NAME . '.motifs.json')) {
    // Load from this cabin's motif cache.
    $motifs = \Airship\loadJSON(ROOT.'/tmp/cache/' . CABIN_NAME . '.motifs.json');
    $state->motifs = $motifs;
} elseif (\defined('CABIN_DIR')) {
    // Let's make sure our directories exist:
    if (!\is_dir(CABIN_DIR.'/Lens/motif')) {
        \mkdir(CABIN_DIR.'/Lens/motif', 0775);
    } else {
        \chmod(CABIN_DIR.'/Lens/motif', 0775);
    }
    if (!\is_dir(CABIN_DIR.'/public/motif')) {
        \mkdir(CABIN_DIR.'/public/motif', 0775);
    } else {
        \chmod(CABIN_DIR.'/public/motif', 0775);
    }
    
    // Parse the Cabin's Motifs configuration file:
    $motifsJSONFile = ROOT . '/Cabin/' . CABIN_NAME . '/config/motifs.json';
    if (\is_dir(CABIN_DIR.'/Lens/motif') && \is_readable($motifsJSONFile)) {
        $motifs = [];
        $motifsJSONData = \Airship\loadJSON($motifsJSONFile);

        // Parse a particular motif:
        foreach ($motifsJSONData as $motif => $motifConfig) {
            if (isset($motifConfig['path'])) {
                $motifStart = CABIN_DIR . '/Lens/motif/' . $motif;
                $motifEnd = ROOT . '/Motifs/' . $motifConfig['path'];

                // If the Motif is malicious, alert.
                if (\strpos($motifStart, CABIN_DIR . '/Lens/motif') === false) {
                    $state->logger->alert(
                        'Potential directory trasversal in Motif config.',
                        [
                            'cabin' => $active['name'],
                            'motif' => $motif,
                            'path' => $motifStart
                        ]
                    );

                    // SKIP! We have a potential directory traversal
                    continue;
                }
                if (\strpos($motifEnd, ROOT . '/Motifs') === false) {
                    $state->logger->alert(
                        'Potential directory traversal in Motif config.',
                        [
                            'cabin' => $active['name'],
                            'motif' => $motif,
                            'path' => $motifEnd
                        ]
                    );

                    // SKIP! We have a potential directory traversal
                    continue;
                }
                
                // Create the necessary symlinks if they do not already exist:
                if (!\is_link($motifStart)) {
                    \symlink($motifEnd, $motifStart);
                }
                if (\is_dir($motifEnd.'/public')) {
                    $motifPublic = CABIN_DIR.'/public/motif/'.$motif;
                    if (!\is_link($motifPublic)) {
                        \symlink($motifEnd.'/public', $motifPublic);
                    }
                }

                // Finally, load the configuration:
                if (\file_exists($motifEnd.'/motif.json')) {
                    $motifConfig['config'] = \Airship\loadJSON($motifEnd.'/motif.json');
                } else {
                    $motifConfig['config'] = [];
                }
                
                $motifs[$motif] = $motifConfig;
            }
        }

        // Let's save the cache file
        \file_put_contents(
            ROOT.'/tmp/cache/' . CABIN_NAME . '.motifs.json',
            \json_encode($motifs, JSON_PRETTY_PRINT)
        );
        $state->motifs = $motifs;
    } else {
        die(\__("FATAL ERROR: Motifs file is not readable"));
    }
}

if (isset($_settings['active_motif'])) {
    $lens->setBaseTemplate($_settings['active_motif']);
}