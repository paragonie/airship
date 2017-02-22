<?php
declare(strict_types=1);
namespace Airship\Cabin\Hull\Model;

use Airship\Engine\Model;
use Airship\Engine\Gears;

if (!\class_exists('ModelGear')) {
    Gears::extract('Model', 'ModelGear', __NAMESPACE__);
    // Make autocomplete work with existing IDEs:
    if (IDE_HACKS) {
        /**
         * Class ModelGear
         * @package Airship\Cabin\Hull\Model
         */
        class ModelGear extends Model
        {

        }
    }
}
