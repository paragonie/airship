<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Controller;

use Airship\Engine\{
    Controller,
    Gears,
    State
};

if (!\class_exists('ControllerGear')) {
    Gears::extract('Controller', 'ControllerGearBase', __NAMESPACE__);

    // Make autocomplete work with existing IDEs:
    if (IDE_HACKS) {
        /** @noinspection PhpMultipleClassesDeclarationsInOneFile */
        /**
         * Class ControllerGearBase
         * @package Airship\Cabin\Bridge\Controller
         */
        class ControllerGearBase extends Controller { }
    }

    /** @noinspection PhpMultipleClassesDeclarationsInOneFile */
    /**
     * This is a common landing gear for all Bridge components
     *
     * Class ControllerGear
     * @package Airship\Cabin\Bridge\Controller
     */
    class ControllerGear extends ControllerGearBase
    {
        /**
         * This function is called after the dependencies have been injected by
         * AutoPilot. Think of it as a user-land constructor.
         */
        public function airshipLand()
        {
            parent::airshipLand();
            $state = State::instance();

            $cabin_names = [];
            foreach ($state->cabins as $c) {
                $cabin_names [] = $c['name'];
            }

            $this->airship_view_object->store('state', [
                'cabins' => $state->cabins,
                'cabin_names' => $cabin_names,
                'manifest' => $state->manifest
            ]);
        }
    }
}

if (!\class_exists('LoggedInUsersOnly')) {
    /** @noinspection PhpMultipleClassesDeclarationsInOneFile */
    /**
     * Class LoggedInUsersOnly
     * @package Airship\Cabin\Bridge\Controller
     */
    class LoggedInUsersOnly extends ControllerGear
    {
        /**
         * This function is called after the dependencies have been injected by
         * AutoPilot. Think of it as a user-land constructor.
         */
        public function airshipLand()
        {
            parent::airshipLand();
            $this->storeViewVar('showmenu', true);

            if (!$this->isLoggedIn()) {
                // You need to log in first!
                \Airship\redirect($this->airship_cabin_prefix);
            } elseif (!$this->can('read') && !$this->can('index')) {
                // Sorry, you can't read this?
                \Airship\redirect($this->airship_cabin_prefix . '/error/?' . \http_build_query([
                    'error' => '403 Forbidden'
                ]));
            }
        }
    }
}
if (!\class_exists('AdminOnly')) {
    /** @noinspection PhpMultipleClassesDeclarationsInOneFile */
    /**
     * Class AdminOnly
     * @package Airship\Cabin\Bridge\Controller
     */
    class AdminOnly extends LoggedInUsersOnly
    {
        /**
         * This function is called after the dependencies have been injected by
         * AutoPilot. Think of it as a user-land constructor.
         */
        public function airshipLand()
        {
            parent::airshipLand();

            if (!$this->isSuperUser()) {
                \Airship\redirect($this->airship_cabin_prefix);
            }
        }
    }
}