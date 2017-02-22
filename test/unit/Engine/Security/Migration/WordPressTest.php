<?php
declare(strict_types=1);

use Airship\Engine\Security\{
    Migration\WordPress
};
use ParagonIE\Halite\HiddenString;
use ParagonIE\Halite\Symmetric\EncryptionKey;
use PHPUnit\Framework\TestCase;

/**
 * @backupGlobals disabled
 * @covers HiddenString
 */
class WordPressTest extends TestCase
{
    public function testImport()
    {
        $migrate = new WordPress();
        $migrate->setPasswordKey(
            new EncryptionKey(new HiddenString(\str_repeat("\x00", 32)))
        );

        list ($newHash, $data) = $migrate->getHashWithMetadata(
            '$P$BhLUOfHf5srnKHKWEu19tJSmGKTbgX.'
        );
        $this->assertTrue(
            $migrate->validate(
                new HiddenString('apple'),
                $newHash,
                $data
            )
        );

        $this->assertFalse(
            $migrate->validate(
                new HiddenString('hunter2'),
                $newHash,
                $data
            )
        );
        $this->assertFalse(
            $migrate->validate(
                new HiddenString(''),
                $newHash,
                $data
            )
        );
    }
}
