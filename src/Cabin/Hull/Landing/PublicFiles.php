<?php
declare(strict_types=1);
namespace Airship\Cabin\Hull\Landing;

use \Airship\Alerts\FileSystem\FileNotFound;
use \Airship\Alerts\Router\EmulatePageNotFound;

require_once __DIR__.'/gear.php';

/**
 * Class PublicFiles
 * @package Airship\Cabin\Hull\Landing
 */
class PublicFiles extends LandingGear
{
    protected $cabin = 'Hull';

    public function airshipLand()
    {
        parent::airshipLand();
        $this->files = $this->blueprint('PublicFiles');
    }

    /**
     * Download a file (assuming we are allowed to)
     *
     * @param string $path
     * @route files/(.*)
     * @throws EmulatePageNotFound
     */
    public function download(string $path)
    {
        if (!$this->can('read')) {
            throw new EmulatePageNotFound;
        }
        $pieces = \Airship\chunk($path);
        $filename = \array_pop($pieces);
        try {
            $filedata = $this->files->getFileInfo($this->cabin, $pieces, \urldecode($filename));
            $realpath = AIRSHIP_UPLOADS  . $filedata['realname'];

            if (!\file_exists($realpath)) {
                throw new FileNotFound();
            }
            // All text/whatever needs to be text/plain; no HTML or JS payloads allowed
            if (substr($filedata['type'], 0, 5) === 'text/' || \strpos($filedata['type'], 'application') !== false) {
                $p = \strpos($filedata['type'], ';');
                if ($p !== false) {
                    $filedata['type'] = 'text/plain; ' .
                        \preg_replace(
                            '#[^A-Za-z0-9/]#',
                            '',
                            \substr($filedata['type'], $p)
                        );
                } else {
                    $filedata['type'] = 'text/plain';
                }
            }

            $c = $this->config('file.cache');
            if ($c > 0) {
                // Use caching
                \header('Cache-Control: private, max-age=' . $c);
                \header('Pragma: cache');
            }

            // Serve the file
            \header('Content-Type: ' . $filedata['type']);
            \readfile($realpath);
            exit;
        } catch (FileNotFound $ex) {
            // When all else fails, 404 not found
            \header('HTTP/1.1 404 Not Found');
            $this->lens('404');
            exit;
        }
    }
}
