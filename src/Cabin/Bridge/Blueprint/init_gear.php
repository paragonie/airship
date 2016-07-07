<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Blueprint;

use Airship\Engine\{
    Blueprint,
    Gears
};

if (!\class_exists('BlueprintGear')) {
    Gears::extract('Blueprint', 'BlueprintGear', __NAMESPACE__);
    // Make autocomplete work with existing IDEs:
    if (IDE_HACKS) {
        /**
         * Class BlueprintGear
         * @package Airship\Cabin\Bridge\Blueprint
         */
        class BlueprintGear extends Blueprint
        {

        }
    }
}
