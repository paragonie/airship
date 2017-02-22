<?php
declare(strict_types=1);
namespace Airship\Cabin\Hull\Controller;

use Airship\Engine\{
    Controller,
    Gears
};

if (!\class_exists('ControllerGear')) {
    Gears::extract('Controller', 'ControllerGear', __NAMESPACE__);
    // Make autocomplete work with existing IDEs:
    if (IDE_HACKS) {
        /**
         * Class ControllerGear
         * @package Airship\Cabin\Hull\Controller
         */
        class ControllerGear extends Controller
        {

        }
    }
}
