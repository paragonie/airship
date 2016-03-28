<?php
declare(strict_types=1);
namespace Airship\Hangar\Commands;

use Airship\Engine\Continuum\Airship;
use Airship\Hangar\SessionCommand;

class Autorun extends SessionCommand
{
    public $essential = false;
    public $display = 4;
    public $name = 'Add Autorun Script';
    public $description = 'Add scripts to run after the update has complete.';

    /**
     * Fire the add command to add files and directories to this update pack.
     *
     * @param array $args
     * @return bool
     */
    public function fire(array $args = []): bool
    {
        try {
            $this->getSession();
            $dir = $this->session['dir'] . $this->findRelativeDir();
        } catch (\Error $e) {
            echo $e->getMessage(), "\n";
            return false;
        }

        if (\count($args) === 0) {
            echo 'No file passed.', "\n";
            return false;
        }
        if (!\array_key_exists('autorun', $this->session)) {
            // echo 'Creating session data', "\n";
            $this->session['autorun'] = [];
        }

        $added = 0;
        foreach ($args as $file) {
            $l = \strlen($file) - 1;
            if ($file[$l] === DIRECTORY_SEPARATOR) {
                $file = substr($file, 0, -1);
            }
            $added += $this->addAutorun($file, $dir);
        }
        echo $added, ' autorun script', ($added === 1 ? '' : 's'), ' registered.', "\n";
        return true;
    }

    /**
     * Add a file or directory to the
     *
     * @param string $filename
     * @param string $dir
     * @return int
     */
    protected function addAutorun(string $filename, string $dir): int
    {
        if (!empty($dir)) {
            if ($dir[\strlen($dir) - 1] !== DIRECTORY_SEPARATOR) {
                $dir .= DIRECTORY_SEPARATOR;
            }
        }
        if (!\file_exists($filename)) {
            echo $this->c['red'], 'File not found: ', $this->c[''], $filename, "\n";
            return 0;
        }

        try {
            $path = $this->getRealPath(\realpath($filename));
        } catch (\Error $e) {
            echo $this->c['red'], $e->getMessage(), $this->c[''], "\n";
            return 0;
        }

        if (\array_key_exists($path, $this->session['autorun'])) {
            echo $this->c['yellow'], 'Autorun script already registered: ', $this->c[''], $path, "\n";
            return 0;
        }

        // Recursive adding
        if (\is_dir($path)) {
            echo $this->c['red'], 'You cannot add a directory to an autorun script: ', $this->c[''], $path, "\n";
            return 0;
        }
        $this->session['autorun'][$path] = [
            'type' => $this->getType($path),
            'data' => Base64::_encode(\file_get_contents($path))
        ];
        echo $this->c['green'], 'Autorun script registered: ', $this->c[''], $path, "\n";
        return 1;
    }

    /**
     * Get information about a script
     *
     * @param string $path
     * @return string
     * @throws \Error
     */
    protected function getType(string $path): string
    {
        $ds = \preg_quote(DIRECTORY_SEPARATOR, '#');
        if (\preg_match('#'.$ds.'.*?\.(.*)#', $path, $matches)) {
            $t = \strtolower($matches[1]);
            switch ($t) {
                case 'php':
                case 'php3':
                case 'phtml':
                case 'inc':
                    return 'php';
                case 'mysql':
                    return 'mysql';
                case 'pgsql':
                    return 'pgsql';
                case 'sh':
                    return 'sh';
                default:
                    throw new \Error('Unknown script type: '.$t);
            }
        }
        throw new \Error('Unknown script type');
    }
}
