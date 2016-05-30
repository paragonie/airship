<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Landing;

use \Airship\Engine\{
    Gears,
    Landing,
    State
};

if (!\class_exists('LandingGear')) {
    Gears::extract('Landing', 'LandingGearBase', __NAMESPACE__);

    // Make autocomplete work with existing IDEs:
    if (IDE_HACKS) {
        /**
         * Class LandingGearBase
         * @package Airship\Cabin\Bridge\Landing
         */
        class LandingGearBase extends Landing { }
    }

    /**
     * This is a common landing gear for all Bridge components
     *
     * Class LandingGear
     * @package Airship\Cabin\Bridge\Landing
     */
    class LandingGear extends LandingGearBase
    {
        /**
         * This method gets invoked by the router before the final method call
         * of the HTTP request.
         */
        public function airshipLand()
        {
            parent::airshipLand();
            $state = State::instance();

            $cabin_names = [];
            foreach ($state->cabins as $c) {
                $cabin_names [] = $c['name'];
            }

            $this->airship_lens_object->store('state', [
                'cabins' => $state->cabins,
                'cabin_names' => $cabin_names,
                'manifest' => $state->manifest
            ]);
        }
    }
}

if (!\class_exists('LoggedInUsersOnly')) {
    /**
     * Class LoggedInUsersOnly
     * @package Airship\Cabin\Bridge\Landing
     */
    class LoggedInUsersOnly extends LandingGear
    {
        /**
         * We aren't letting no one access this unless they log in first!
         */
        public function airshipLand()
        {
            parent::airshipLand();

            if (!$this->isLoggedIn()) {
                // You need to log in first!
                \Airship\redirect($this->airship_cabin_prefix);
            } elseif (!$this->can('read') && !$this->can('index')) {
                // Sorry, you can't read this?
                \Airship\redirect($this->airship_cabin_prefix . '/?' . \http_build_query([
                    'error' => '403 Forbidden'
                ]));
            }
        }
    }
}
if (!\class_exists('AdminOnly')) {
    /**
     * Class AdminOnly
     * @package Airship\Cabin\Bridge\Landing
     */
    class AdminOnly extends LoggedInUsersOnly
    {
        /**
         * We aren't letting no one access this unless they log in first!
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