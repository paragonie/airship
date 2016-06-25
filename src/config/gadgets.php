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
if (\file_exists(ROOT . '/config/gadgets.json')) {
    $globalGadgets = \Airship\loadJSON(ROOT . '/config/gadgets.json');
} else {
    \file_put_contents(ROOT . '/config/gadgets.json', '[]');
    $globalGadgets = [];
}
foreach ($globalGadgets as $i => $gadgetConfig) {
    if (!$gadgetConfig['enabled']) {
        continue;
    }
    $phar = \implode(
        DIRECTORY_SEPARATOR,
        [
            ROOT,
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

if (\file_exists(ROOT . '/Cabin/' . $active['name'] . '/config/gadgets.json')) {
    $cabinsGadgets = \Airship\loadJSON(
        ROOT . '/Cabin/' . $active['name'] . '/config/gadgets.json'
    );
} else {
    $cabinsGadgets = [];
    \file_get_contents(
        ROOT . '/Cabin/' . $active['name'] . '/config/gadgets.json',
        '[]'
    );
}
foreach ($cabinsGadgets as $i => $gadgetConfig) {
    if (!$gadgetConfig['enabled']) {
        continue;
    }
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
