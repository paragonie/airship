<?php
declare(strict_types=1);
namespace Airship\Cabin\Hull\Controller;

use Airship\Cabin\Hull\Model as BP;
use Airship\Alerts\{
    FileSystem\FileNotFound,
    Router\ControllerComplete,
    Router\EmulatePageNotFound
};
use Airship\Engine\Model;
use Airship\Engine\Security\Util;

require_once __DIR__.'/init_gear.php';

/**
 * Class PublicFiles
 * @package Airship\Cabin\Hull\Controller
 */
class PublicFiles extends ControllerGear
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
     * @var string[]
     */
    protected $viewableMimeTypes = [
        'image/gif',
        'image/jpg',
        'image/jpeg',
        'image/png',
        'text/plain'
    ];

    /**
     * This function is called after the dependencies have been injected by
     * AutoPilot. Think of it as a user-land constructor.
     */
    public function airshipLand()
    {
        parent::airshipLand();
        $files = $this->model('PublicFiles');
        if (!($files instanceof BP\PublicFiles)) {
            throw new \TypeError(Model::TYPE_ERROR);
        }
        $this->files = $files;
    }

    /**
     * Download a file (assuming we are allowed to)
     *
     * @param string $path
     * @param string $default Default MIME type
     * @return void
     * @route files/(.*)
     * @throws ControllerComplete
     * @throws EmulatePageNotFound
     */
    public function download(string $path, string $default = 'text/plain'): void
    {
        if (!$this->can('read')) {
            throw new EmulatePageNotFound();
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
            
            $fileData['type'] = Util::downloadFileType($fileData['type'], $default);

            $c = $this->config('file.cache');
            if ($c > 0) {
                // Use caching
                \header('Cache-Control: private, max-age=' . $c);
                \header('Pragma: cache');
            }

            /**
             * The following headers are necessary because Microsoft Internet
             * Explorer has a documented design flaw that, left unhandled, can
             * introduce a stored XSS vulnerability. The recommended solution
             * would be "never use Internet Explorer", but some people don't have
             * a choice in that matter.
             */
            if (!$this->isViewable($fileData['type'])) {
                \header('Content-Disposition: attachment; filename="' . \urlencode($fileData['filename']) . '"');
                \header('X-Download-Options: noopen');
            }
            $this->includeStandardHeaders($fileData['type']);
            if (!\is_null($this->airship_response)) {
                \Airship\sendHeaders($this->airship_response);
            }
            \readfile($realPath);
            exit;
        } catch (FileNotFound $ex) {
            // When all else fails, 404 not found
            \http_response_code(404);
            $this->view('404');
            exit(1);
        }
    }

    /**
     * @param string $mimeHeader
     * @return bool
     */
    protected function isViewable(string $mimeHeader): bool
    {
        $pos = \strpos($mimeHeader, ';');
        if ($pos !== false) {
            $mimeHeader = Util::subString($mimeHeader, 0, $pos);
        }
        return \in_array($mimeHeader, $this->viewableMimeTypes, true);
    }
}
