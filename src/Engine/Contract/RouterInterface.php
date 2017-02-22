<?php
declare(strict_types=1);
namespace Airship\Engine\Contract;
use Psr\Http\Message\{
    ResponseInterface,
    ServerRequestInterface
};

/**
 * Interface RouterInterface
 * @package Airship\Engine\Contract
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
    public static function testController(
        string $path,
        string $uri,
        array &$args = [],
        bool $needsPrep = false
    ): bool;

    /**
     * This method should fly your guest to their designated landing.
     */
    public function route(ServerRequestInterface $request): ResponseInterface;
}
