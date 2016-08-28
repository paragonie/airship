<?php
declare(strict_types=1);

use Airship\Engine\{
    Lens,
    State
};

/**
 * @global Lens $lens
 * @global array $_settings
 * @global array $active
 * @global State $state
 *
 * Load this in index.php, or anywhere /AFTER/ the CABIN_DIR constant exists
 */
if (\defined('CABIN_DIR')) {
    $motifCacheFile = ROOT . '/tmp/cache/' . CABIN_NAME . '.motifs.json';
    if (\file_exists($motifCacheFile) && \filesize($motifCacheFile) > 0) {
        // Load from this cabin's motif cache.
        $motifs = \Airship\loadJSON($motifCacheFile);
        $state->motifs = $motifs;
    } else {
        // Let's make sure our directories exist:
        if (!\is_dir(CABIN_DIR . '/Lens/motif')) {
            \mkdir(CABIN_DIR . '/Lens/motif', 0775);
        } else {
            @\chmod(CABIN_DIR . '/Lens/motif', 0775);
        }
        if (!\is_dir(CABIN_DIR . '/public/motif')) {
            \mkdir(CABIN_DIR . '/public/motif', 0775);
        } else {
            @\chmod(CABIN_DIR . '/public/motif', 0775);
        }

        // Parse the Cabin's Motifs configuration file:
        $motifsJSONFile = ROOT . '/Cabin/' . CABIN_NAME . '/config/motifs.json';
        if (\is_dir(CABIN_DIR . '/Lens/motif') && \is_readable($motifsJSONFile)) {
            $motifs = [];
            $motifsJSONData = \Airship\loadJSON($motifsJSONFile);

            // Parse a particular motif:
            foreach ($motifsJSONData as $motif => $motifConfig) {
                if (empty($motifConfig['enabled'])) {
                    continue;
                }
                if (isset($motifConfig['path'])) {
                    $motifStart = CABIN_DIR . '/Lens/motif/' . $motif;
                    $motifEnd = ROOT . '/Motifs/' . $motifConfig['path'];

                    // If the Motif is malicious, alert.
                    if (\strpos($motifStart, CABIN_DIR . '/Lens/motif') === false) {
                        $state->logger->alert(
                            'Potential directory traversal in Motif config.',
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
                        \symlink($motifEnd . '/lens', $motifStart);
                    }
                    if (\is_dir($motifEnd . '/public')) {
                        $motifPublic = CABIN_DIR . '/public/motif/' . $motif;
                        if (!\is_link($motifPublic)) {
                            \symlink($motifEnd . '/public', $motifPublic);
                        }
                    }

                    // Finally, load the configuration:
                    if (\file_exists($motifEnd . '/motif.json')) {
                        $motifConfig['config'] = \Airship\loadJSON($motifEnd . '/motif.json');
                    } else {
                        $motifConfig['config'] = [];
                    }

                    $motifs[$motif] = $motifConfig;
                }
            }
            \Airship\saveJSON($motifCacheFile, $motifs);
            $state->motifs = $motifs;
        } else {
            die(\__("FATAL ERROR: Motifs file is not readable"));
        }
    }
}

$userMotif = \Airship\LensFunctions\user_motif();
if (!empty($userMotif)) {
    $activeMotif = $userMotif['name'];
} elseif (isset($_settings['active-motif'])) {
    $activeMotif = $_settings['active-motif'];
}
if (isset($activeMotif)) {
    $lens
        ->setBaseTemplate($activeMotif)
        ->loadMotifCargo($activeMotif)
        ->loadMotifConfig($activeMotif)
        ->addGlobal('MOTIF', $state->motif_config);
}
