<?php
declare(strict_types=1);
namespace Airship\Hangar\Commands;

use \Airship\Hangar\Command;

class Start extends Command
{
    public $essential = true;
    public $display = 3;
    public $name = 'Start Session';
    public $description = 'Start a Hangar session.';

    /**
     * Execute the start command, which will start a new hangar session.
     *
     * @param array $args
     * @return bool
     */
    public function fire(array $args = []): bool
    {
        if (\is_readable(AIRSHIP_LOCAL_CONFIG.'/hangar.session.json')) {
            if (\count($args) > 0) {
                if ($args[0] !== '--force') {
                    echo 'There is already an active session!';
                    return false;
                }
                $size = \filesize(AIRSHIP_LOCAL_CONFIG . '/hangar.session.json');
                \file_put_contents(
                    AIRSHIP_LOCAL_CONFIG . '/hangar.session.json',
                    \random_bytes($size)
                );
                \unlink(AIRSHIP_LOCAL_CONFIG . '/hangar.session.json');
                \file_put_contents(
                    AIRSHIP_LOCAL_CONFIG.'/hangar.session.json',
                    \json_encode(['dir' => \getcwd()], JSON_PRETTY_PRINT)
                );
                \clearstatcache();
                return true;
            } else {
                echo 'There is already an active session!', "\n";
                echo 'To nuke the active session, run "hangar start --force"', "\n";
                \clearstatcache();
                return false;
            }
        }
        \file_put_contents(
            AIRSHIP_LOCAL_CONFIG.'/hangar.session.json',
            \json_encode(['dir' => \getcwd()], JSON_PRETTY_PRINT)
        );
        echo '[', $this->c['green'], 'OK', $this->c[''], '] ',
            'Session started: ', \getcwd(), "\n";
        \clearstatcache();
        return true;
    }
}
