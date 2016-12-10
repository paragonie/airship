<?php
declare(strict_types=1);
namespace Airship\Engine\Security\Migration;

use ParagonIE\ConstantTime\Binary;
use ParagonIE\Halite\{
    HiddenString,
    Password,
    Symmetric\EncryptionKey
};

/**
 * Class WordPress
 * @package Airship\Engine\Security\Migration
 */
class WordPress implements MigrationInterface
{
    public const TYPE = 'wordpress';

    /**
     * @var EncryptionKey
     */
    protected $key;

    /**
     * @var string
     */
    protected $itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    /**
     * Use during imports to populate a table with metadata and a rehashed hash.
     *
     * @param string $oldHash
     * @param EncryptionKey $passwordKey
     * @return array [HiddenString, array]
     * @throws \Exception
     */
    public function getHashWithMetadata(string $oldHash, EncryptionKey $passwordKey = null): array
    {
        if (!$passwordKey) {
            if (!($this->key instanceof EncryptionKey)) {
                throw new \Exception(
                    \__('No key was passed to this migration')
                );
            }
            $passwordKey = $this->key;
        }
        return [
            new HiddenString(Password::hash(new HiddenString($oldHash), $passwordKey)),
            [
                'type' => self::TYPE,
                'salt' => Binary::safeSubstr($oldHash, 0, 12)
            ]
        ];
    }

    /**
     * @param EncryptionKey $passwordKey
     * @return MigrationInterface
     */
    public function setPasswordKey(EncryptionKey $passwordKey): MigrationInterface
    {
        $this->key = $passwordKey;
        return $this;
    }

    /**
     * Validate a user-provided password with user
     *
     * @param HiddenString $password
     * @param HiddenString $pHash
     * @param array $migrationData
     * @param EncryptionKey $passwordKey
     * @return bool
     * @throws \Exception
     */
    public function validate(
        HiddenString $password,
        HiddenString $pHash,
        array $migrationData,
        EncryptionKey $passwordKey = null
    ): bool {
        if (!$passwordKey) {
            if (!($this->key instanceof EncryptionKey)) {
                throw new \Exception(
                    \__('No key was passed to this migration')
                );
            }
            $passwordKey = $this->key;
        }
        $hash = $this->wordPressCryptPrivate($password, $migrationData['salt']);

        return Password::verify(
            $hash,
            $pHash->getString(),
            $passwordKey
        );
    }

    /**
     * WordPress's internal password hashing algorithm. Only used for migrations.
     * The actual security of CMS Airship doesn't depend on this algorithm.
     *
     * @internal
     * @param HiddenString $password
     * @param string $setting
     * @return HiddenString
     */
    private function wordPressCryptPrivate(
        HiddenString $password,
        string $setting
    ): HiddenString {
        $output = '*0';
        if (Binary::safeSubstr($setting, 0, 2) === $output) {
            $output = '*1';
        }
        $id = Binary::safeSubstr($setting, 0, 3);

        if ($id !== '$P$' && $id !== '$H$') {
            return new HiddenString($output);
        }

        // This is a really weird way to encode iteration count.
        $count_log2 = \strpos($this->itoa64, $setting[3]);
        if ($count_log2 < 7 || $count_log2 > 30) {
            return new HiddenString($output);
        }

        $count = 1 << $count_log2;
        $salt = Binary::safeSubstr($setting, 4, 8);
        if (Binary::safeStrlen($salt) !== 8) {
            return new HiddenString($output);
        }

        // And now we do our (default 8192) rounds of MD5...
        $hash = \md5($salt . $password->getString(), true);
        do {
            $hash = \md5($hash . $password->getString(), true);
        } while (--$count);

        $output = Binary::safeSubstr($setting, 0, 12);
        $output .= $this->encode64($hash, 16);
        return new HiddenString($output);
    }

    /**
     * Wordpress's specific variant of Base64DotSlash encoding.
     *
     * @param string $input
     * @param int $count
     * @return string
     */
    private function encode64(string $input, int $count)
    {
        $output = '';
        $i = 0;
        do {
            $value = \ord($input[$i++]);
            $output .= $this->itoa64[$value & 0x3f];
            if ($i < $count)
                $value |= \ord($input[$i]) << 8;
            $output .= $this->itoa64[($value >> 6) & 0x3f];
            if ($i++ >= $count)
                break;
            if ($i < $count)
                $value |= \ord($input[$i]) << 16;
            $output .= $this->itoa64[($value >> 12) & 0x3f];
            if ($i++ >= $count)
                break;
            $output .= $this->itoa64[($value >> 18) & 0x3f];
        } while ($i < $count);
        return $output;
    }
}
