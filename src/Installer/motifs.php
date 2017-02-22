<?php
declare(strict_types=1);

use Airship\Engine\State;

/**
 * @global array $active
 * @global State $state
 */

foreach (['Hull', 'Bridge'] as $cabinName) {
    $cabinDir = ROOT . '/Cabin/' . $cabinName;
    // Let's make sure our directories exist:
    if (!\is_dir($cabinDir.'/View/motif')) {
        \mkdir($cabinDir.'/View/motif', 0775);
    } else {
        \chmod($cabinDir.'/View/motif', 0775);
    }
    if (!\is_dir($cabinDir.'/public/motif')) {
        \mkdir($cabinDir.'/public/motif', 0775);
    } else {
        \chmod($cabinDir.'/public/motif', 0775);
    }

    // Now let's set up our Motifs:
    $motifsJSONFile = ROOT . '/config/Cabin/' . $cabinName . '/motifs.json';
    if (\is_dir($cabinDir.'/View/motif') && \is_readable($motifsJSONFile)) {
        $motifs = [];
        $motifsJSONData = \Airship\loadJSON($motifsJSONFile);

        foreach ($motifsJSONData as $motif => $motifConfig) {
            if (isset($motifConfig['path'])) {
                $motifStart = $cabinDir.'/View/motif/'.$motif;
                $motifEnd = ROOT.'/Motifs/'.$motifConfig['path'];

                if (\strpos($motifStart, $cabinDir.'/View/motif') === false) {
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
                if (\strpos($motifEnd, ROOT.'/Motifs') === false) {
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

                // Make sure we create the necessary symlinks
                if (!\is_link($motifStart)) {
                    \symlink($motifEnd, $motifStart);
                }
                if (!\is_link(ROOT . '/public/static/' . $cabinName)) {
                    \symlink(
                        $cabinDir . '/public',
                        ROOT . '/public/static/' . $cabinName
                    );
                }

                if (\is_dir($motifEnd.'/public')) {
                    $motifPublic = $cabinDir.'/public/motif/'.$motif;
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
    }
}
