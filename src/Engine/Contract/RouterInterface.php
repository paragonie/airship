<?php
declare(strict_types=1);
namespace Airship\Engine\Contract;

/**
 * An interface for database interaction.
 */
interface RouterInterface
{
    /**
     * You should be able to pass cabin configurations here
     * 
     * @param array $cabins
     */
    public function __construct(array $cabins = []);

    /**
     * Test a path against a URI
     *
     * @param string $path
     * @param string $uri
     * @param array $args
     * @param bool $needsPrep
     * @return bool
     */
    public static function testLanding(
        string $path,
        string $uri,
        array &$args = [],
        bool $needsPrep = false
    ): bool;

    /**
     * 
     */
    public function route();
}