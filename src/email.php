<?php
declare(strict_types=1);

use Airship\Engine\State;
use ParagonIE\GPGMailer\GPGMailer;

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
        if (empty($state->universal['email']['transport'])) {
            // Sane default
            $transport = new Zend\Mail\Transport\Sendmail();
        } else {
            switch ($state->universal['email']['transport']) {
                case 'SMTP':
                    $transport = new \Zend\Mail\Transport\Smtp();
                    $transportConfig = [
                        'name' =>
                            $state->universal['email']['smtp']['name'],
                        'host' =>
                            $state->universal['email']['smtp']['host'],
                        'connection_class' =>
                            $state->universal['email']['smtp']['connection_class']
                    ];
                    if ($state->universal['email']['smtp']['connection_class'] !== 'smtp') {
                        $transportConfig['connection_config'] = [
                            'username' =>
                                $state->universal['email']['smtp']['username'],
                            'password' =>
                                $state->universal['email']['smtp']['password']
                        ];
                    }
                    
                    if (!empty($state->universal['email']['smtp']['disable_tls'])) {
                        $transportConfig['connection_config']['port'] = !empty($state->universal['email']['smtp']['port'])
                            ? $state->universal['email']['smtp']['port']
                            : 25;
                    } else {
                        $transportConfig['connection_config']['ssl'] = 'tls';
                        $transportConfig['port'] = !empty($state->universal['email']['smtp']['port'])
                            ? $state->universal['email']['smtp']['port']
                            : 587;
                    }
                    $transport->setOptions(
                        new \Zend\Mail\Transport\SmtpOptions($transportConfig)
                    );
                    break;

                case 'File':
                    $transport = new Zend\Mail\Transport\File();
                    /** @noinspection PhpUnusedParameterInspection */
                    $transport->setOptions(
                        new \Zend\Mail\Transport\FileOptions([
                            'path' =>
                                !empty($state->universal['email']['file']['path'])
                                    ? $state->universal['email']['file']['path']
                                    : ROOT . '/files/email',
                            'callback' =>
                                function (Zend\Mail\Transport\File $t) {
                                    return \implode(
                                        '_',
                                        [
                                            'Message',
                                            \date('YmdHis'),
                                            \Airship\uniqueId(12) . '.txt'
                                        ]
                                    );
                                }
                        ])
                    );
                    break;

                case 'Sendmail':
                    if (!empty($state->universal['email']['sendmail']['parameters'])) {
                        $transport = new Zend\Mail\Transport\Sendmail(
                            $state->universal['email']['sendmail']['parameters']
                        );
                    } else {
                        $transport = new Zend\Mail\Transport\Sendmail();
                    }
                    break;

                default:
                    throw new Exception(
                        \sprintf(
                            'Miconfigured Email Configuration -- unknown transport: %s',
                            \print_r($state->universal['email']['transport'], true)
                        )
                    );
            }
        }
        $state->mailer = $transport;
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