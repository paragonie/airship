<?php
declare(strict_types=1);
namespace Airship\Hangar\Commands;

use Airship\Hangar\Command;
use ParagonIE\Halite\{
    File,
    KeyFactory,
    SignatureKeyPair
};

/**
 * Class Sign
 * @package Airship\Hangar\Commands
 */
class Sign extends Command
{
    /**
     * @var bool
     */
    public $essential = true;

    /**
     * @var int
     */
    public $display = 7;

    /**
     * @var string
     */
    public $name = 'Sign';

    /**
     * @var string
     */
    public $description = 'Cryptographically sign an update file.';

    /**
     * @var array
     */
    protected $history = [];

    /**
     * Execute the start command, which will start a new hangar session.
     *
     * @param array $args
     * @return bool
     * @throws \Error
     */
    public function fire(array $args = []): bool
    {
        $file = $this->selectFile($args[0] ?? '');
        if (!isset($this->config['salt']) && \count($args) < 2) {
            throw new \Error('No salt configured or passed');
        }
        if (\count($args) > 2) {
            switch (\strtolower($args[2])) {
                case 'fast':
                case 'i':
                case 'interactive':
                case 'weak':
                    $level = KeyFactory::INTERACTIVE;
                    break;
                case 'm':
                case 'signing':
                case 'moderate':
                    $level = KeyFactory::MODERATE;
                    break;
                default:
                    $level = KeyFactory::SENSITIVE;
                    break;
            }
        } elseif (isset($this->config['keytype'])) {
            switch ($this->config['keytype']) {
                case 'fast':
                case 'i':
                case 'interactive':
                case 'weak':
                    $level = KeyFactory::INTERACTIVE;
                    break;
                case 'm':
                case 'signing':
                case 'moderate':
                    $level = KeyFactory::MODERATE;
                    break;
                default:
                    $level = KeyFactory::SENSITIVE;
                    break;
            }
        } else {
            $level = KeyFactory::SENSITIVE;
        }
        $salt = \Sodium\hex2bin($args[1] ?? $this->config['salt']);

        echo 'Generating a signature for: ', $file, "\n";
        $password = $this->silentPrompt('Enter password: ');

        // We can get rid of the 'false' in version 2.0.0 (with Halite 3)
        $sign_kp = KeyFactory::deriveSignatureKeyPair(
            $password,
            $salt,
            false,
            $level
        );
        if (!($sign_kp instanceof SignatureKeyPair)) {
            throw new \Error('Error during key derivation');
        }

        $signature = File::sign(
            $file,
            $sign_kp->getSecretKey()
        );
        if (isset($this->history)) {
            $this->config['build_history']['signed'] = true;
        }
        \file_put_contents($file.'.sig', $signature);
        echo 'File signed: ' . $file.'.sig', "\n";
        echo 'Public key: ' . \Sodium\bin2hex(
            $sign_kp->getPublicKey()->getRawKeyMaterial()
        ), "\n";
        return true;
    }

    /**
     * Select which file to sign
     *
     * @param string $filename
     * @return string
     * @throws \Error
     */
    protected function selectFile(string $filename = ''): string
    {
        if (!empty($filename)) {
            // Did we get passed an absolute path?
            if ($filename[0] === '/') {
                if (!\file_exists($filename)) {
                    throw new \Error('File not found: ' . $filename);
                }
                return $filename;
            }

            $dir = \getcwd();
            $path = \realpath($dir . DIRECTORY_SEPARATOR . $filename);
            if (!\file_exists($path)) {
                // Ok, try in the Airship config directory then?
                $path = \realpath(AIRSHIP_LOCAL_CONFIG . DIRECTORY_SEPARATOR . $filename);
                if (!\file_exists($path)) {
                    throw new \Error('File not found: ' . $filename);
                }
            }
            return $path;
        }

        // Let's grab it from our build history then, eh?
        $files = $this->config['build_history'] ?? [];
        if (empty($files)) {
            throw new \Error('No recent builds. Try specifying ');
        }
        $keys = \array_keys($files);
        $this->history = \array_pop($keys);
        $last = $files[$this->history];
        return $last['path'];
    }
}
