<?php
declare(strict_types=1);

use \Airship\Engine\State;
/**
 * @global State $state
 */

// Start the session
if (!\session_id()) {
    $session_config = [
        // Prevent uninitialized sessions from being accepted
        'use_strict_mode' => true,
        // 32 bytes = 256 bits, which mean a 50% chance of 1 collision after 2^128 sessions
        'entropy_length' => 32,
        // The session ID cookie should be inaccessible to JavaScript
        'cookie_httponly' => true
    ];
    if (isset($state->session_config)) {
        /** @noinspection PhpMethodParametersCountMismatchInspection */
        \session_start($state->session_config + $session_config);
    } else {
        /** @noinspection PhpMethodParametersCountMismatchInspection */
        \session_start($session_config);
    }
}

if (empty($_SESSION['created_canary'])) {
    // We haven't seen this session ID before
    $oldSession = $_SESSION;
    // Make sure $_SESSION is empty before we regenerate IDs
    $_SESSION = [];
    \session_regenerate_id(true);
    // Now let's restore the superglobal
    $_SESSION = $oldSession;
    // Create the canary
    $_SESSION['created_canary'] = (new \DateTime('NOW'))
        ->format('Y-m-d\TH:i:s');
} else {
    $dt = (
        new \DateTime($_SESSION['created_canary'])
    )->add(
        new \DateInterval('PT01H')
    );
    $now = new \DateTime('now');
    // Has an hour passed?
    if ($dt < $now) {
        // We haven't seen this session ID before
        $oldSession = $_SESSION;
        // An hour has passed:
        // Make sure $_SESSION is empty before we regenerate IDs
        $_SESSION = [];
        \session_regenerate_id(true);
        // Now let's restore the superglobal
        $_SESSION = $oldSession;
        // Create the canary
        $_SESSION['created_canary'] = $now->format('Y-m-d\TH:i:s');
    }
}
