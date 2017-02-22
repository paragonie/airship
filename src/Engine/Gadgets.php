<?php
declare(strict_types=1);
namespace Airship\Engine;

use Airship\Alerts\Security\SecurityAlert;
use Airship\Engine\Security\Migration\MigrationInterface;

/**
 * Class Gadgets
 *
 * This abstract class simply contains some methods useful for Gadget development
 *
 * @package Airship\Engine
 */
abstract class Gadgets
{
    /**
     * Set the base template
     *
     * @param string $path
     * @return void
     */
    public static function setBaseTemplate(string $path)
    {
        $state = State::instance();
        $state->base_template = $path;
    }

    /**
     * Inject one or more routes to the current Cabin's autopilot route list
     *
     * @param array $injected
     */
    public static function injectRoutes(array $injected = [])
    {
        $state = State::instance();
        $merged = \array_merge(
            $state->injectRoutes ?? [],
            $injected
        );
        $state->injectRoutes = $merged;
    }
    
    /**
     * Store cargo to be rendered into a template
     * 
     * @param string $name
     * @param string $source File path (can be within phar://)
     */
    public static function loadCargo(string $name, string $source)
    {
        $state = State::instance();
        $cargo = isset($state->cargo)
            ? $state->cargo
            : [];
        $cargoIterator = isset($state->cargoIterator)
            ? $state->cargoIterator
            : [];
        
        if (!\array_key_exists($name, $cargo)) {
            $cargo[$name] = [];
        }

        \array_unshift($cargo[$name], $source);
        $cargo[$name] = \array_values($cargo[$name]);

        if (!\in_array($name, $cargoIterator)) {
            $cargoIterator[$name] = 0;
        }
        $state->cargoIterator = $cargoIterator;
        $state->cargo = $cargo;
    }

    /**
     * Register a migration class
     *
     * @param string $key
     * @param MigrationInterface $migration
     */
    public static function registerMigration(string $key, MigrationInterface $migration)
    {
        $state = State::instance();
        $registry = $state->migrations ?? [];
        if (!isset($registry[$key])) {
            $registry[$key] = $migration;
            $state->migrations = $registry;
        }
    }

    /**
     * Find a migration class
     *
     * @param string $key
     * @return MigrationInterface
     * @throws SecurityAlert
     */
    public static function loadMigration(string $key): MigrationInterface
    {
        $state = State::instance();
        $registry = $state->migrations ?? [];
        if (!isset($registry[$key])) {
            throw new SecurityAlert(
                \__('Migration (%s) not found', 'default', $key)
            );
        }
        return $registry[$key];
    }

    /**
     * Render the contents of the next cargo (placeholder)
     *
     * @param string $name
     * @return array
     */
    public static function unloadNextCargo(string $name)
    {
        $state = State::instance();
        $iterate = $state->cargoIterator;

        if (isset($iterate[$name])) {
            ++$iterate[$name];
            $cargo = self::unloadCargo($name, $iterate[$name]);
            $state->cargoIterator = $iterate;
            return $cargo;
        }
        return [];
    }
    
    /**
     * Render the contents of a cargo (placeholder)
     * 
     * @param string $name
     * @param int $offset
     * @return array
     */
    public static function unloadCargo(string $name, int $offset = 0)
    {
        $state = State::instance();
        if (isset($state->cargo[$name])) {
            if (isset($state->cargo[$name][$offset])) {
                // Return an entire slice; Twig will use the first valid result
                return \array_slice($state->cargo[$name], $offset);
            }
            return $state->cargo[$name];
        }
        return [];
    }
}
