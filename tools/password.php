<?php
declare(strict_types=1);

use Airship\Engine\State;
use ParagonIE\Halite\Password;

require_once \dirname(__DIR__).'/src/bootstrap.php';

/**
 * Generate an encrypted password hash from the command line.
 */

$state = State::instance();

$hash = Password::hash($argv[1], $state->keyring['auth.password_key']);
if (Password::verify($argv[1], $hash, $state->keyring['auth.password_key'])) {
    echo $hash, "\n";
    exit(0);
} else {
    echo 'Unexpected ciphertext corruption. Is the password key correct?', "\n";
    exit(255);
}
