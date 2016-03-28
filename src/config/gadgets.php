<?php
/**
 * Let's make sure we autoload all of the relevant templates
 * 
 * @globalvar $twigLoader
 */

/**
 * Autoload all of the universal gadgets
 */
foreach (glob(ROOT.'/Gadgets/') as $supplier) {
    foreach (glob($supplier.'/*.phar') as $phar) {
        $twigLoader->addPath('phar://'.$phar.'/Lens/', $supplier);
        if (\file_exists('phar://'.$phar.'/autoload.php')) {
            include 'phar://'.$phar.'/autoload.php';
        }
        if (\file_exists('phar://'.$phar.'/lens.php')) {
            $lensLoad []= 'phar://'.$phar.'/lens.php';
        }
    }
}


/**
 * Autoload all of the gadgets for the current cabin
 */

foreach (glob(ROOT.'/Cabin/'.$active['name']) as $supplier) {
    foreach (glob($supplier.'/*.phar') as $phar) {
        $twigLoader->addPath('phar://'.$phar.'/Lens/', $supplier);
        if (\file_exists('phar://'.$phar.'/autoload.php')) {
            include 'phar://'.$phar.'/autoload.php';
        }
        if (\file_exists('phar://'.$phar.'/lens.php')) {
            $lensLoad []= 'phar://'.$phar.'/lens.php';
        }
    }
}