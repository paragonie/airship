<?php
declare(strict_types=1);
namespace Airship\Cabin\Hull\Blueprint;

use \Airship\Engine\Blueprint;
use \Airship\Engine\Gears;

if (!\class_exists('BlueprintGear')) {
    Gears::extract('Blueprint', 'BlueprintGear', __NAMESPACE__);
    // Make autocomplete work with existing IDEs:
    if (IDE_HACKS) {
        class BlueprintGear extends Blueprint
        {

        }
    }
}
