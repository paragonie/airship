<?php
declare(strict_types=1);
namespace Airship\Cabin\Hull\Landing;

use \Airship\Cabin\Hull\Blueprint as BP;
use \Airship\Alerts\{
    FileSystem\FileNotFound,
    Router\EmulatePageNotFound
};

require_once __DIR__.'/init_gear.php';

/**
 * Class PublicFiles
 * @package Airship\Cabin\Hull\Landing
 */
class PublicFiles extends LandingGear
{
    /**
     * @var string
     */
    protected $cabin = 'Hull';

    /**
     * @var BP\PublicFiles
     */
    protected $files;

    /**
     * This function is called after the dependencies have been injected by
     * AutoPilot. Think of it as a user-land constructor.
     */
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
            $fileData = $this->files->getFileInfo(
                $this->cabin,
                $pieces,
                \urldecode($filename)
            );
            $realPath = AIRSHIP_UPLOADS  . $fileData['realname'];

            if (!\file_exists($realPath)) {
                throw new FileNotFound();
            }
            // All text/whatever needs to be text/plain; no HTML or JS payloads allowed
            if (
                \substr($fileData['type'], 0, 5) === 'text/'
                    ||
                \strpos($fileData['type'], 'application') !== false
                    ||
                \strpos($fileData['type'], 'xml') !== false
                    ||
                \strpos($fileData['type'], 'svg') !== false
            ) {
                $p = \strpos($fileData['type'], ';');
                if ($p !== false) {
                    $fileData['type'] = 'text/plain; ' .
                        \preg_replace(
                            '#[^A-Za-z0-9/=]#',
                            '',
                            \substr($fileData['type'], $p)
                        );
                } else {
                    $fileData['type'] = 'text/plain';
                }
            }

            $c = $this->config('file.cache');
            if ($c > 0) {
                // Use caching
                \header('Cache-Control: private, max-age=' . $c);
                \header('Pragma: cache');
            }

            // Serve the file
            \header('Content-Type: ' . $fileData['type']);
            $this->airship_lens_object->sendStandardHeaders($fileData['type']);
            \readfile($realPath);
            exit;
        } catch (FileNotFound $ex) {
            // When all else fails, 404 not found
            \header('HTTP/1.1 404 Not Found');
            $this->lens('404');
            exit;
        }
    }
}
