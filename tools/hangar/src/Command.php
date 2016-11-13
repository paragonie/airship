<?php
declare(strict_types=1);
namespace Airship\Hangar;

use ParagonIE\ConstantTime\Binary;

/**
 * Class Command
 * @package Airship\Hangar
 */
abstract class Command
{
    const TAB_SIZE = 8;

    /**
     * @var bool
     */
    public $essential = false;

    /**
     * @var int
     */
    public $display = 65535;

    /**
     * @var string
     */
    public $name = 'CommandName';

    /**
     * @var string
     */
    public $description = 'CLI description';

    /**
     * @var array
     */
    public $tag = [
        'color' => '',
        'text' => ''
    ];

    /**
     * Cache references to other commands
     *
     * @var array
     */
    public static $cache = [];

    /**
     * Current user's configuration
     *
     * @var = []
     */
    public static $userConfig = [];

    /**
     * hangar.json
     *
     * @var array
     */
    protected $config = [];

    /**
     * hangar.session.json
     *
     * @var array
     */
    public $session = [];

    /**
     * BASH COLORS
     *
     * @var array
     */
    protected $c = [
        '' => "\033[0;39m",
        'red'       => "\033[0;31m",
        'green'     => "\033[0;32m",
        'blue'      => "\033[1;34m",
        'cyan'      => "\033[1;36m",
        'silver'    => "\033[0;37m",
        'yellow'    => "\033[0;93m"
    ];

    /**
     * Execute a command
     *
     * @return bool
     */
    abstract public function fire(array $args = []): bool;

    /**
     * Return the size of the current terminal window
     *
     * @return array (int, int)
     */
    public function getScreenSize(): array
    {
        $output = [];
        \preg_match_all(
            "/rows.([0-9]+);.columns.([0-9]+);/",
            \strtolower(\exec('stty -a |grep columns')),
            $output
        );
        if (\sizeof($output) === 3) {
            return [
                'width' => $output[2][0],
                'height' => $output[1][0]
            ];
        }
        return [80, 25];
    }

    /**
     * Return a command
     *
     * @param string $name
     * @param boolean $cache
     * @return Command (derived class)
     */
    public function getCommandObject($name, $cache = true): self
    {
        return self::getCommandStatic($name, $cache);
    }

    /**
     * Get a token for HTTP requests
     *
     * @param string $supplier
     * @return string
     */
    public function getToken($supplier): string
    {
        if (!isset($this->config['suppliers'][$supplier])) {
            return '';
        }
        if (empty($this->config['suppliers'][$supplier]['token'])) {
            return '';
        }
        $v = $this->config['suppliers'][$supplier]['token'];
        return $v['selector'].':'.$v['validator'];
    }

    /**
     * Return a command (statically callable)
     *
     * @param string $name
     * @param boolean $cache
     * @return \Airship\Hangar\Command
     */
    public static function getCommandStatic($name, $cache = true)
    {
        $_name = '\\Airship\\Hangar\\Commands\\'.\ucfirst($name);
        if (!empty(self::$cache[$name])) {
            return self::$cache[$name];
        }
        if ($cache) {
            self::$cache[$name] = new $_name;
            return self::$cache[$name];
        }
        return new $_name;
    }

    /**
     * @param array $data
     */
    final public function storeConfig(array $data = [])
    {
        $this->config = $data;
    }

    /**
     * Save the configuration
     */
    final public function saveConfig()
    {
        \file_put_contents(
            AIRSHIP_LOCAL_CONFIG."/hangar.json",
            \json_encode($this->config, JSON_PRETTY_PRINT)
        );
        if (!empty($this->session)) {
            \file_put_contents(
                AIRSHIP_LOCAL_CONFIG . "/hangar.session.json",
                \json_encode($this->session, JSON_PRETTY_PRINT)
            );
        }
    }

    /**
     * Get the session data
     *
     * @return mixed
     * @throws \Error
     */
    final protected function getSession()
    {
        if (!@\is_readable(AIRSHIP_LOCAL_CONFIG.'/hangar.session.json')) {
            throw new \Error('There is no active Hangar session');
        }
        $session = \file_get_contents(AIRSHIP_LOCAL_CONFIG.'/hangar.session.json');
        return $this->session = \json_decode($session, true);
    }

    /**
     * Prompt the user for an input value
     *
     * @param string $text
     * @return string
     */
    final protected function prompt($text)
    {
        static $fp = null;
        if ($fp === null) {
            $fp = \fopen('php://stdin', 'r');
        }
        echo $text;
        return Binary::safeSubstr(\fgets($fp), 0, -1);
    }


    /**
     * Interactively prompts for input without echoing to the terminal.
     * Requires a bash shell or Windows and won't work with
     * safe_mode settings (Uses `shell_exec`)
     *
     * @ref http://www.sitepoint.com/interactive-cli-password-prompt-in-php/
     */
    final protected function silentPrompt(string $text = "Enter Password:"): string
    {
        if (\preg_match('/^win/i', PHP_OS)) {
            $vbscript = sys_get_temp_dir() . 'prompt_password.vbs';
            \file_put_contents(
                $vbscript,
                'wscript.echo(InputBox("'. \addslashes($text) . '", "", "password here"))'
            );
            $command = "cscript //nologo " . \escapeshellarg($vbscript);
            $password = \rtrim(
                \shell_exec($command)
            );
            \unlink($vbscript);
            return $password;
        } else {
            $command = "/usr/bin/env bash -c 'echo OK'";
            if (\rtrim(\shell_exec($command)) !== 'OK') {
                throw new \Exception("Can't invoke bash");
            }
            $command = "/usr/bin/env bash -c 'read -s -p \"". addslashes($text). "\" mypassword && echo \$mypassword'";
            $password = \rtrim(shell_exec($command));
            echo "\n";
            return $password;
        }
    }

    /**
     * Display the usage information for this command.
     *
     * @param array $args - CLI arguments
     * @echo
     * @return bool
     */
    public function usageInfo(array $args = [])
    {
        unset($args);
        $TAB = \str_repeat(' ', self::TAB_SIZE);
        $HTAB = \str_repeat(' ', (int) ceil(self::TAB_SIZE / 2));

        echo $HTAB, 'Airship / Hangar - ', $this->name, "\n\n";
        echo $TAB, $this->description, "\n\n";
        return true;
    }
}
