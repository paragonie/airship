<?php
declare(strict_types=1);
namespace Airship\Hangar\Commands;

use Airship\Hangar\Command;

/**
 * Class Help
 * @package Airship\Hangar\Commands
 */
class Help extends Command
{
    /**
     * @var bool
     */
    public $essential = false;

    /**
     * @var int
     */
    public $display = 1;

    /**
     * @var string
     */
    public $name = 'Command Reference';

    /**
     * @var string
     */
    public $description = 'Display information about Hangar command line options.';

    /**
     * @var bool
     */
    public $showAll = true;

    /**
     * @var string[]
     */
    private $label = [
        'topCommands' => 'Essential/Popular Commands:',
        'allCommands' => 'All Commands:'
    ];

    /**
     * @var array
     */
    private $commands = [];

    /**
     * Preamble before firing is done here
     */
    public function __construct(array $commands = [])
    {
        $this->commands = $commands;
    }

    /**
     * Execute the help command
     *
     * @param array $args - CLI arguments
     * @echo
     * @return bool
     * @throws \Error
     */
    public function fire(array $args = []): bool
    {
        $command = !empty($args[0]) ? $args[0] : null;

        if (!empty($command)) {
            if (empty($this->commands[$command])) {
                throw new \Error('Command '.$command.' not found!');
            }

            $com = $this->getCommandObject($this->commands[$command]);
            $com->usageInfo($args);
            echo $this->c[''];
            return true;
        }
        $this->usageInfo($args);
        $w = $this->getScreenSize()['width'];
        echo "\n", str_repeat('_', $w - 1), "\n", $this->c[''];
        return true;
    }

    /**
     * Display the main help menu
     *
     * @echo
     * @return void
     */
    public function helpMenu()
    {
        $essential = [];
        $coms = [];
        $columns = [8, 4, 11];

        foreach ($this->commands as $i => $name) {
            if (\strlen($i) > $columns[0]) {
                $columns[0] = \strlen($i);
            }
            if ($name === 'Help') {
                if (\strlen($this->name) > $columns[1]) {
                    $columns[1] = \strlen($this->name);
                }
                if (\strlen($this->description) > $columns[2]) {
                    $columns[2] = \strlen($this->description);
                }
                $coms[$i] = [
                    'name' => $this->name,
                    'description' => $this->description,
                    'display' => $this->display
                ];
            } else {
                $com = $this->getCommandObject($name);
                if (\strlen($com->name) > $columns[1]) {
                    $columns[1] = \strlen($com->name);
                }

                // $descr is just for length calculations
                // $details is with the tag
                $descr = $com->description;
                $details = $com->description;
                if (!empty($com->tag['text'])) {
                    $descr = '['.$com->tag['text'].'] '.$descr;
                    $details = $this->c[$com->tag['color']].
                        '['.
                        $com->tag['text'].
                        ']'.
                        $this->c[''].
                        ' '.
                        $com->description;
                }
                if (\strlen($descr) > $columns[2]) {
                    $columns[2] = \strlen($descr);
                }


                if ($com->essential) {
                    $essential[$i] = [
                        'name' => $com->name,
                        'description' => $details,
                        'display' => $com->display
                    ];
                }
                $coms[$i] = [
                    'name' => $com->name,
                    'description' => $details,
                    'display' => $com->display
                ];
                unset($com);
            }
        }

        \uasort($essential, [$this, 'sortCommands']);
        \uasort($coms, [$this, 'sortCommands']);

        $width = $this->getScreenSize()['width'];

        // $desiredWidth = array_sum($columns) + (3 * self::TAB_SIZE);
        $wrap = $width - $columns[1] - $columns[0] - (3 * self::TAB_SIZE) - 1;

        // Prevent wrapping because of newline characters
        --$columns[2];

        $repeatPad = \str_repeat(' ', $columns[0] + $columns[1] + (3 * self::TAB_SIZE));
        $TAB = \str_repeat(' ', self::TAB_SIZE);
        $HTAB = \str_repeat(' ', (int) ceil(self::TAB_SIZE / 2));

        $header = $this->c['blue'].
            $TAB.
            \str_pad('Command', $columns[0], ' ', STR_PAD_RIGHT).
            $TAB.
            \str_pad('Name', $columns[1], ' ', STR_PAD_RIGHT).
            $TAB.
            'Description'.
            $this->c[''].
            "\n".
            $TAB . \str_repeat('=', $width - self::TAB_SIZE - 1)."\n";

        echo $this->c[''], $HTAB, "How to use one of the commands in the table below:\n";
        echo $TAB, $this->c['cyan'], "hangar [command]", $this->c[''], "\n";
        echo $TAB, $HTAB, "Run the command.";
        echo "\n\n";

        echo $TAB, $this->c['cyan']."hangar help [command]", $this->c[''], "\n";
        echo $TAB, $HTAB, "Display usage information for a specific command.";
        echo "\n\n";

        echo $HTAB, $this->label['topCommands'], "\n";
        echo $header;

        $newline = false;
        foreach ($essential as $k => $com) {
            if ($newline) {
                echo "\n", $TAB, \str_repeat('-', $width - self::TAB_SIZE - 1), "\n";
            }
            echo $TAB;
            echo $this->c['yellow'].
                \str_pad($k, $columns[0], ' ', STR_PAD_RIGHT).
                $this->c[''];
            echo $TAB;
            echo \str_pad($com['name'], $columns[1], ' ', STR_PAD_RIGHT);
            echo $TAB;
            echo \wordwrap($com['description'], $wrap, "\n".$repeatPad, true);
            $newline = true;
        }
        if (!$this->showAll) {
            echo "\n\n", $HTAB, 'To view all of the available commands, run this command: ';
            echo $this->c['cyan'], 'hangar help', $this->c[''];
            return;
        }

        echo "\n\n", $HTAB, $this->label['allCommands'], "\n";
        echo $header;

        $nl = false;
        foreach ($coms as $k => $com) {
            if ($nl) {
                echo "\n", $TAB, \str_repeat('-', $width - self::TAB_SIZE - 1), "\n";
            }
            echo $TAB;
            echo "\033[0;93m", \str_pad($k, $columns[0], ' ', STR_PAD_RIGHT), "\033[0;39m";
            echo $TAB;
            echo \str_pad($com['name'], $columns[1], ' ', STR_PAD_RIGHT);
            echo $TAB;
            echo \wordwrap($com['description'], $wrap, "\n".$repeatPad, true);
            $nl = true;
        }
    }

    /**
     * Used for uasort() calls in this class
     *
     * @param array $a
     * @param array $b
     * @return int
     */
    public function sortCommands(array $a, array $b): int
    {
        if ($a['display'] > $b['display']) {
            return 1;
        }
        if ($a['display'] < $b['display']) {
            return -1;
        }
        return (int) ($a['name'] <=> $b['name']);
    }

    /**
     * Display the usage information for this command.
     *
     * @param array $args - CLI arguments
     * @echo
     * @return void
     */
    public function usageInfo(array $args = [])
    {
        if (\count($args) == 0) {
            $this->helpMenu();
            return;
        }
        if (\strtolower($args[0]) !== 'help') {
            foreach ($this->commands as $i => $name) {
                if (\strtolower($args[0]) === $i) {
                    $com = $this->getCommandObject($name);
                    $com->usageInfo(
                        \array_values(
                            \array_slice($args, 1)
                        )
                    );
                    return;
                }
            }
        }
        // Now let's actually print the usage info for this class

        $TAB = \str_repeat(' ', self::TAB_SIZE);
        $HTAB = \str_repeat(' ', (int) \ceil(self::TAB_SIZE / 2));

        echo $HTAB, $this->name, "\n";
        echo $TAB, $this->description, "\n\n";
        echo $HTAB, "How to use this command:\n";
        echo $TAB, $this->c['cyan'], "hangar ", $this->c[''], "\n";
        echo $TAB, $this->c['cyan'], "hangar help", $this->c[''], "\n";
        echo $TAB, $HTAB, "List all of the commands available to hangar.";
        echo "\n";

        echo $TAB, $this->c['cyan']."hangar help [command]", $this->c[''], "\n";
        echo $TAB, $HTAB, "Display usage information for a specific command.";
        echo "\n";

        echo "\n";
    }
}
