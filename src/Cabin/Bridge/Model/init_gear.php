<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Model;

use Airship\Engine\{
    Model,
    Gears
};

if (!\class_exists('ModelGear')) {
    Gears::extract('Model', 'ModelGear', __NAMESPACE__);
    // Make autocomplete work with existing IDEs:
    if (IDE_HACKS) {
        /**
         * Class ModelGear
         * @package Airship\Cabin\Bridge\Model
         */
        class ModelGear extends Model
        {

        }
    }
}
