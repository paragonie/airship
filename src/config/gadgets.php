<?php
/**
 * Let's make sure we autoload all of the relevant templates
 *
 * @global array $lensLoad
 * @global array $active
 * @global \Twig_Loader_Filesystem $twigLoader
 */

/**
 * Autoload all of the universal gadgets
 */
foreach (\glob(ROOT . '/Gadgets/') as $supplier) {
    if (!\is_dir($supplier)) {
        continue;
    }
    $supplierName = \Airship\path_to_filename($supplier);
    foreach (\glob($supplier . '/*.phar') as $phar) {
        $gadgetName = \Airship\path_to_filename($phar, true);
        $namespace = \preg_replace(
            '/[^A-Za-z0-9\-_]/',
            '_',
            $supplierName . '__' . $gadgetName
        );
        $twigLoader->addPath('phar://' . $phar . '/Lens/', $namespace);
        // phar:///path/to/foo.phar/autoload.php
        if (\file_exists('phar://' . $phar . '/autoload.php')) {
            include 'phar://' . $phar . '/autoload.php';
        }
        // phar:///path/to/foo.phar/lens.php
        if (\file_exists('phar://' . $phar . '/lens.php')) {
            $lensLoad []= 'phar://' . $phar . '/lens.php';
        }
    }
}

$cabinsGadgets = \Airship\loadJSON(
    ROOT . '/Cabin/' . $active['name'] . '/config/gadgets.json'
);
foreach ($cabinsGadgets as $i => $gadgetConfig) {
    $phar = \implode(
        DIRECTORY_SEPARATOR,
        [
            ROOT,
            'Cabin',
            $active['name'],
            'Gadgets',
            $gadgetConfig['supplier'],
            $gadgetConfig['supplier'] . '.' . $gadgetConfig['name'] . '.phar'
        ]
    );
    $namespace = $gadgetConfig['namespace']
        ?? \preg_replace(
            '/[^A-Za-z0-9\-_]/',
            '_',
            $gadgetConfig['supplier'] . '__' . $gadgetConfig['name']
        );
    $twigLoader->addPath('phar://' . $phar . '/Lens/', $namespace);
    // phar:///path/to/foo.phar/autoload.php
    if (\file_exists('phar://' . $phar . '/autoload.php')) {
        include 'phar://' . $phar . '/autoload.php';
    }
    // phar:///path/to/foo.phar/lens.php
    if (\file_exists('phar://' . $phar . '/lens.php')) {
        $lensLoad []= 'phar://' . $phar . '/lens.php';
    }
}
