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
                $this->reset();
                break;
            default:
                $this->usage($argv[1], ...$args);
        }
    }

    /**
     * 
     */
    public function reset()
    {
        \file_put_contents(
            ROOT.'/tmp/installing.json',
            \json_encode(['step' => 0], JSON_PRETTY_PRINT)
        );
        \chmod(ROOT.'/tmp/installing.json', 0777);

        if (\is_link(ROOT.'/public/launch.php')) {
            if (\realpath(ROOT.'/public/launch.php') === ROOT.'/Installer/launch.php') {
                return;
            }
        }

        if (\file_exists(ROOT.'/public/launch.php')) {
            \unlink(ROOT.'/public/launch.php');
            \clearstatcache();
        }

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
