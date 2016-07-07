<?php
declare(strict_types=1);

use Airship\Engine\{
    AutoPilot,
    State
};
/**
 * @global State $state
 */

// Start the session
if (!\session_id()) {
    if (!isset($state)) {
        $state = State::instance();
    }
    $session_config = [
        // Prevent uninitialized sessions from being accepted
        'use_strict_mode' => true,
        // We don't need to specify entropy_file; it defaults to /dev/urandom
        // 32 bytes = 256 bits, which mean a 50% chance of 1 collision after 2^128 sessions
        'entropy_length' => 32,
        // The session ID cookie should be inaccessible to JavaScript
        'cookie_httponly' => true,
        // If we're over HTTPS, enforce secure=1
        'cookie_secure' => AutoPilot::isHTTPSConnection()
    ];
    if (isset($state->universal['session_config'])) {
        $session_config = $state->universal['session_config'] + $session_config;
        if (isset($session_config['cookie_domain'])) {
            if ($session_config['cookie_domain'] === '*' || $session_config['cookie_domain'] === '') {
                unset($session_config['cookie_domain']);
            }
        }
    }
    /** @noinspection PhpMethodParametersCountMismatchInspection */
    \session_start($session_config);
}

if (empty($_SESSION['created_canary'])) {
    // We haven't seen this session ID before
    $_SESSION = [];
    \session_regenerate_id(true);
    // Create the canary
    $_SESSION['created_canary'] = (new \DateTime())
        ->format(\AIRSHIP_DATE_FORMAT);
} else {
    $dt = (new \DateTime($_SESSION['created_canary']))->add(
        new \DateInterval('PT01H')
    );
    $now = new \DateTime();
    // Has an hour passed?
    if ($dt < $now) {
        // An hour has passed:
        \session_regenerate_id(true);
        // Create the canary
        $_SESSION['created_canary'] = $now->format(\AIRSHIP_DATE_FORMAT);
    }
}
