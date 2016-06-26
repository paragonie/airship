<?php
declare(strict_types=1);

foreach (\glob(ROOT . '/Cabin/*') as $dir) {
    if (\is_dir($dir)) {
        $name = \Airship\path_to_filename($dir);
        if (!\is_link(ROOT . '/public/static/' . $name)) {
            if (\is_dir(ROOT . '/public/static/' . $name)) {
                \rmdir(ROOT . '/public/static/' . $name);
            } elseif (\file_exists(ROOT . '/public/static/' . $name)) {
                \unlink(ROOT . '/public/static/' . $name);
            }
            \symlink(
                ROOT . '/Cabin/' . $name . '/public',
                ROOT . '/public/static/' . $name
            );
        }

        // Editor templates.
        if (!\is_link(ROOT . '/Installer/skins/cabin_links/' . $name)) {
            if (\is_dir(ROOT . '/Installer/skins/cabin_links/' . $name)) {
                \rmdir(ROOT . '/Installer/skins/cabin_links/' . $name);
            } elseif (\file_exists(ROOT . '/Installer/skins/cabin_links/' . $name)) {
                \unlink(ROOT . '/Installer/skins/cabin_links/' . $name);
            }
            \symlink(
                ROOT . '/Cabin/' . $name . '/config/editor_templates',
                ROOT . '/Installer/skins/cabin_links/' . $name
            );
        }

        // Any Motifs we ship with are suitable for all of the Cabins we ship with.
        // Less configuration headaches.
        foreach (\glob(ROOT . '/Motifs/*') as $motifDir) {
            if (\is_dir($motifDir)) {
                $supplier = \Airship\path_to_filename($motifDir);

                foreach (\glob($motifDir . '/*') as $sub) {
                    $motif = \Airship\path_to_filename($sub);
                    $linkFrom = $dir . '/public/motif/' . $motif;
                    $n = 1;
                    while (\is_link($linkFrom)) {
                        if (\realpath($linkFrom) !== \realpath($sub)) {
                            ++$n;
                            $linkFrom = $dir . '/public/motif/' . $motif . '-' . $n;
                        } else {
                            break;
                        }
                    }
                    \symlink($sub . '/public', $linkFrom);
                }

            }
        }
    }
}