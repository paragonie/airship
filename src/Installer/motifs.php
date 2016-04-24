<?php
declare(strict_types=1);

foreach (['Hull', 'Bridge'] as $cabinName) {
    $cabinDir = ROOT . '/Cabin/' . $cabinName;
    // Let's make sure our directories exist:
    if (!\is_dir($cabinDir.'/Lens/motif')) {
        \mkdir($cabinDir.'/Lens/motif', 0775);
    } else {
        \chmod($cabinDir.'/Lens/motif', 0775);
    }
    if (!\is_dir($cabinDir.'/public/motif')) {
        \mkdir($cabinDir.'/public/motif', 0775);
    } else {
        \chmod($cabinDir.'/public/motif', 0775);
    }

    // Now let's set up our Motifs:
    $motifsJSONFile = ROOT . '/config/Cabin/' . $cabinName . '/motifs.json';
    if (\is_dir($cabinDir.'/Lens/motif') && \is_readable($motifsJSONFile)) {
        $motifs = [];
        $motifsJSONData = \Airship\loadJSON($motifsJSONFile);

        foreach ($motifsJSONData as $motif => $motifConfig) {
            if (isset($motifConfig['path'])) {
                $motifStart = $cabinDir.'/Lens/motif/'.$motif;
                $motifEnd = ROOT.'/Motifs/'.$motifConfig['path'];

                if (\strpos($motifStart, $cabinDir.'/Lens/motif') === false) {
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
