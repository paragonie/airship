<?php
declare(strict_types=1);
namespace Airship\Installer;

/**
 * Class Commands
 * @package Airship\Installer
 */
class Commands
{
    /**
     * Commands constructor.
     * @param array $argv
     */
    public function __construct(array $argv = [])
    {
        $args = \array_slice($argv, 2);
        switch ($argv[1]) {
            case 'reset':
                $this->reset(...$args);
                break;
            default:
                $this->usage($argv[1], ...$args);
        }
    }

    /**
     * @param array ...$args
     */
    public function reset(...$args)
    {
        \file_put_contents(
            ROOT.'/tmp/installing.json',
            \json_encode(['step' => 0], JSON_PRETTY_PRINT)
        );
        \chmod(ROOT.'/tmp/installing.json', 0777);
        \chown(ROOT.'/tmp/installing.json', 'www-data');
        
        \symlink(
            ROOT.'/Installer/launch.php',
            ROOT.'/public/launch.php'
        );
    }

    /**
     * @param $command
     * @param array ...$args
     */
    public function usage($command, ...$args)
    {
        
    }
}
