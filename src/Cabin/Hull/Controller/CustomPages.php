<?php
declare(strict_types=1);
namespace Airship\Cabin\Hull\Controller;

use Airship\Cabin\Hull\Exceptions\{
    CustomPageNotFoundException,
    RedirectException
};
use Airship\Cabin\Hull\Model\CustomPages as PagesModel;
use Airship\Engine\State;
use Gregwar\RST\Parser as RSTParser;
use League\CommonMark\CommonMarkConverter;
use Psr\Log\LogLevel;

require_once __DIR__.'/init_gear.php';

/**
 * Class CustomPages
 *
 * Custom page handler.
 *
 * @package Airship\Cabin\Hull\Controller
 */
class CustomPages extends ControllerGear
{
    /**
     * @var PagesModel
     */
    protected $pages;

    /**
     * @var string
     */
    protected $cabin = 'Hull';

    /**
     * This function is called after the dependencies have been injected by
     * AutoPilot. Think of it as a user-land constructor.
     */
    public function airshipLand()
    {
        $this->pages = $this->model('CustomPages');
        $this->pages->setCabin($this->cabin);
    }

    /**
     * This interrupts requests if all else fails.
     * @param string[] ...$args
     * @return void
     * @throws CustomPageNotFoundException
     */
    public function routeNotFound(...$args)
    {
        if (!\is1DArray($args)) {
            throw new CustomPageNotFoundException(
                \__('Invalid arguments')
            );
        }
        $dirs = $args;
        $file = \array_pop($dirs);

        // First: Do we have a custom page at this endpoint?
        try {
            if ($this->serveCustomPage($file, $dirs)) {
                return;
            }
        } catch (CustomPageNotFoundException $ex) {
            $this->log(
                'Custom page not found',
                LogLevel::INFO,
                [
                    'cabin' => $this->cabin,
                    'dirs' => $dirs,
                    'exception' => \Airship\throwableToArray($ex),
                    'file' => $file
                ]
            );
        }

        // Second: Is there a redirect at this endpoint?
        try {
            $path = \implode('/', $args);
            if ($this->pages->serveRedirect($path)) {
                return;
            }
        } catch (RedirectException $ex) {
            $this->log(
                'Redirect missed',
                LogLevel::INFO
            );
        }

        \http_response_code(404);
        // Finally: Return a 4o4
        $this->lens('404');
        return;
    }

    /**
     * Public API entry point for serving a custom page
     *
     * @param string $file
     * @param array $dirs
     * @return bool
     */
    protected function serveCustomPage(
        string $file,
        array $dirs = []
    ): bool {
        return $this->serveFile(
            $file,
            $this->pages->getParentDir($dirs)
        );
    }

    /**
     * Serve a file
     *
     * @param string $file
     * @param int $directoryId
     * @return bool
     */
    protected function serveFile(
        string $file,
        int $directoryId
    ): bool {
        $page = $this->pages->getPage($file, $directoryId);
        if (!empty($page)) {
            return $this->serveLatestVersion($page);
        }
        return false;
    }

    /**
     * Server the latest version of a custom page.
     *
     * @param array $page
     * @return bool
     */
    protected function serveLatestVersion(array $page): bool
    {
        $latest = $this->pages->getLatestVersion((int) $page['pageid']);
        if (empty($latest)) {
            return false;
        }
        $vars = $latest['metadata'];
        $vars['meta'] = [];
        if (!empty($vars['keywords'])) {
            $vars['meta']['keywords'] = $vars['keywords'];
        }
        if (!empty($vars['description'])) {
            $vars['meta']['description'] = $vars['description'];
        }
        $vars['rendered_content'] = $this->render($latest);

        if ($page['cache']) {
            $this->stasis('custom', $vars);
        } else {
            $this->lens('custom', $vars);
        }
        return true;
    }

    /**
     * Render a custom page
     *
     * @param array $latest
     * @return string
     */
    protected function render(array $latest): string
    {
        $state = State::instance();
        switch ($latest['formatting']) {
            case 'Markdown':
                $md = new CommonMarkConverter;
                if (empty($latest['raw'])) {
                    $state->HTMLPurifier->purify(
                        $md->convertToHtml($latest['body'])
                    );
                }
                return $md->convertToHtml($latest['body']);
            case 'RST':
                $rst = (new RSTParser())
                    ->setIncludePolicy(false);
                if (empty($latest['raw'])) {
                    $state->HTMLPurifier->purify(
                        (string) $rst->parse($latest['body'])
                    );
                }
                return (string) $rst->parse($latest['body']);
            case 'HTML':
            case 'Rich Text':
            default:
                if (empty($latest['raw'])) {
                    return $state->HTMLPurifier->purify($latest['body']);
                }
                return $latest['body'];
        }
    }
}
