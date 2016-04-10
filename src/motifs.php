<?php
declare(strict_types=1);
/**
 * Load this in index.php, or anyhwere /AFTER/ the CABIN_DIR constant exists
 */
if (\defined('CABIN_DIR') && \file_exists(ROOT.'/tmp/cache/' . CABIN_NAME . '.motifs.json')) {
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
    
    // Now let's go set this up...
    $motifsJSONFile = ROOT . '/config/Cabin/' . CABIN_NAME . '/motifs.json';
    if (\is_dir(CABIN_DIR.'/Lens/motif') && \is_readable($motifsJSONFile)) {
        $motifs = [];
        $motifsJSONData = \Airship\loadJSON($motifsJSONFile);

        foreach ($motifsJSONData as $motif => $motifConfig) {
            if (isset($motifConfig['path'])) {
                $motifStart = CABIN_DIR.'/Lens/motif/'.$motif;
                $motifEnd = ROOT.'/Motifs/'.$motifConfig['path'];
                
                if (\strpos($motifStart, CABIN_DIR.'/Lens/motif') === false) {
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
                if (\strpos($motifEnd, ROOT.'/Motifs') === false) {
                    $state->logger->alert(
                        'Potential directory trasversal in Motif config.',
                        [
                            'cabin' => $active['name'],
                            'motif' => $motif,
                            'path' => $motifEnd
                        ]
                    );

                    // SKIP! We have a potential directory traversal
                    continue;
                }
                
                // Make sure we create the necessary symlinks
                if (!\is_link($motifStart)) {
                    \symlink($motifEnd, $motifStart);
                }
                if (\is_dir($motifEnd.'/public')) {
                    $motifPublic = CABIN_DIR.'/public/motif/'.$motif;
                    if (!\is_link($motifPublic)) {
                        \symlink($motifEnd.'/public', $motifPublic);
                    }
                }
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