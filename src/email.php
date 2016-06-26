<?php
declare(strict_types=1);

use \Airship\Engine\State;
use \ParagonIE\GPGMailer\GPGMailer;

/**
 * Set up our email configuration
 */

$email_closure = function() {
    $state = State::instance();

    /**
     * If this is defined elsewhere, respect it.
     * Otherwise, just use the default (sendmail):
     */
    if (empty($state->mailer)) {
        $state->mailer = new Zend\Mail\Transport\Sendmail();
    }
    $gpgMailer = new GPGMailer(
        $state->mailer,
        [
            'homedir' =>
                ROOT . '/files'
        ]
    );
    $state->gpgMailer = $gpgMailer;
};
$email_closure();
unset($email_closure);