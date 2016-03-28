<?php
declare(strict_types=1);
namespace Airship\Hangar;

/**
 * For commands that add to the hangar.session.json file
 *
 * Class SessionCommand
 * @package Airship\Hangar
 */
abstract class SessionCommand extends Command
{
    /**
     * Get the difference between the session root dir and the current dir
     *
     * @return string
     * @throws \Error
     */
    protected function findRelativeDir()
    {
        $current = \getcwd();
        if (\strpos($this->session['dir'], $current) === 0) {
            $x = \strlen($this->session['dir']);
            return \substr($current, $x + 1);
        } else {
            throw new \Error('Current path is outside the root directory');
        }
    }


    /**
     * If a file path is absolute, but still in the root, truncate it.
     * If a file path is relative to the root, return it.
     * Otherwise, thorw an error!
     *
     * @param string $file
     * @return string
     * @throws \Error
     */
    protected function getRealPath(string $file): string
    {
        if (!\file_exists($file)) {
            throw new \Error('File not found: '.$file);
        }
        if (\strpos($file, $this->session['dir']) === 0) {
            $x = \strlen($this->session['dir']);
            return \substr($file, $x + 1);
        } elseif ($file[0] !== DIRECTORY_SEPARATOR) {
            return $file;
        } else {
            throw new \Error('File path is outside the root directory: ' . $file);
        }
    }
}
