<?php
declare(strict_types=1);
namespace Airship\Cabin\Hull\Landing;

use \Airship\Engine\Landing;
use \Airship\Engine\Gears;

if (!\class_exists('LandingGear')) {
    Gears::extract('Landing', 'LandingGear', __NAMESPACE__);
    if (IDE_HACKS) {
        class LandingGear extends Landing
        {

        }
    }
}