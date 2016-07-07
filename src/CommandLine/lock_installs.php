<?php
declare(strict_types=1);

use ParagonIE\Halite\Password;
use Airship\Engine\State;

/**
 * This script saves a password hash to the
 */

require_once \dirname(__DIR__).'/bootstrap.php';

if ($argc > 1) {
    $state = State::instance();
    $save = Password::hash(
        $argv[1],
        $state->keyring['auth.password_key']
    );
} else {
    $save = (new \DateTime('now'))
        ->format(\AIRSHIP_DATE_FORMAT);
}

\file_put_contents(
    ROOT . '/config/install.lock',
    $save
);
