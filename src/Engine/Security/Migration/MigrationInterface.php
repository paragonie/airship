<?php
declare(strict_types=1);
namespace Airship\Engine\Security\Migration;

use Airship\Engine\Security\HiddenString;
use ParagonIE\Halite\Symmetric\EncryptionKey;

/**
 * Interface MigrationInterface
 * @package Airship\Engine\Security\Migration
 */
interface MigrationInterface
{
    /**
     * Use during imports to populate a table with metadata and a rehashed hash.
     *
     * @param string $oldHash
     * @param EncryptionKey $passwordKey
     * @return array [HiddenString, array]
     */
    public function getHashWithMetadata(
        string $oldHash,
        EncryptionKey $passwordKey = null
    ): array;

    /**
     * @param EncryptionKey $passwordKey
     * @return MigrationInterface
     */
    public function setPasswordKey(EncryptionKey $passwordKey): self;

    /**
     * Validate a user-provided password with user
     *
     * @param HiddenString $password
     * @param HiddenString $pHash
     * @param array $migrationData
     * @return bool
     */
    public function validate(
        HiddenString $password,
        HiddenString $pHash,
        array $migrationData
    ): bool;
}
