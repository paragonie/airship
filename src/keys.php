<?php
declare(strict_types=1);

use ParagonIE\Halite\KeyFactory;

/**
 * This sets up the contents of our keyring.
 */

$key_management_closure = function() {
    if (!\is_dir(ROOT.'/config/keyring/')) {
        \mkdir(ROOT.'/config/keyring/', 0750);
    }
    $keyRing = \Airship\loadJSON(ROOT . '/config/keyring.json');
    if (empty($keyRing)) {
        // This is critical to Airship's functioning.
        throw new \Error(\trk('errors.crypto.keyring_missing'));
    }

    $state = \Airship\Engine\State::instance();
    $keys = [];

    foreach ($keyRing as $index => $keyConfig) {
        $path = ROOT . '/config/keyring/' . $keyConfig['file'];
        if (\file_exists($path)) {
            // Load it from disk
            switch ($keyConfig['type']) {
                case 'AuthenticationKey':
                    $keys[$index] = KeyFactory::loadAuthenticationKey($path);
                    break;
                case 'EncryptionKey':
                    $keys[$index] = KeyFactory::loadEncryptionKey($path);
                    break;
                case 'EncryptionPublicKey':
                    $keys[$index] = KeyFactory::loadEncryptionPublicKey($path);
                    break;
                case 'EncryptionSecretKey':
                    $keys[$index] = KeyFactory::loadEncryptionSecretKey($path);
                    break;
                case 'SignaturePublicKey':
                    $keys[$index] = KeyFactory::loadSignaturePublicKey($path);
                    break;
                case 'SignatureSecretKey':
                    $keys[$index] = KeyFactory::loadSignatureSecretKey($path);
                    break;
                case 'EncryptionKeyPair':
                    $keys[$index] = KeyFactory::loadEncryptionKeyPair($path);
                    break;
                case 'SignatureKeyPair':
                    $keys[$index] = KeyFactory::loadSignatureKeyPair($path);
                    break;
                default:
                    throw new \Error(
                        \trk('errors.crypto.unknown_key_type', $keyConfig['type'])
                    );
            }
        } else {
            // We must generate this key/keypair at once:
            switch ($keyConfig['type']) {
                case 'EncryptionPublicKey':
                case 'SignaturePublicKey':
                    throw new \Error(
                        \trk('errors.crypto.cannot_generate_public_key')
                    );
                case 'AuthenticationKey':
                    $keys[$index] = KeyFactory::generateAuthenticationKey();
                    KeyFactory::save($keys[$index], $path);
                    break;
                case 'EncryptionKey':
                    $keys[$index] = KeyFactory::generateEncryptionKey();
                    KeyFactory::save($keys[$index], $path);
                    break;
                case 'EncryptionSecretKey':
                    $kp = KeyFactory::generateEncryptionKeyPair();
                    $keys[$index] = $kp->getSecretKey();
                    KeyFactory::save($keys[$index], $path);
                    break;
                case 'SignatureSecretKey':
                    $kp = KeyFactory::generateSignatureKeyPair();
                    $keys[$index] = $kp->getSecretKey();
                    KeyFactory::save($keys[$index], $path);
                    break;
                case 'EncryptionKeyPair':
                    $keys[$index] = KeyFactory::generateEncryptionKeyPair();
                    KeyFactory::save($keys[$index], $path);
                    break;
                case 'SignatureKeyPair':
                    $keys[$index] = KeyFactory::generateSignatureKeyPair();
                    KeyFactory::save($keys[$index], $path);
                    break;
                default:
                    throw new \Error(
                        \trk('errors.crypto.unknown_key_type', $keyConfig['type'])
                    );
            }
        }
    }
    // Now that we have a bunch of Keys stored in $keys, let's load them into
    // our singleton.
    $state->keyring = $keys;
};
$key_management_closure();
unset($key_management_closure);
