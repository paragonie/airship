<?php
declare(strict_types=1);
namespace Airship\Installer;

class Commands
{
    public function __construct(array $argv = [])
    {
        $args = \array_slice($argv, 2);
        switch ($argv[1]) {
            case 'reset':
                return $this->reset(...$args);
            default:
                return $this->usage($argv[1], ...$args);
        }
    }
    
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
    
    public function usage($command, ...$args)
    {
        
    }
}
