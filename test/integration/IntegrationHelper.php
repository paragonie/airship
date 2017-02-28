<?php
declare(strict_types=1);
namespace Airship\IntegrationTests;

use Airship\Alerts\Router\ControllerComplete;
use Airship\Engine\AutoPilot;
use Airship\Engine\Networking\HTTP\ServerRequest;
use Airship\Engine\Networking\HTTP\Uri;
use Airship\Engine\State;
use Psr\Http\Message\ResponseInterface;

/**
 * Class IntegrationHelper
 * @package Airship\IntegrationTests
 */
class IntegrationHelper
{
    /**
     * @param string $uri
     * @param array $headers
     * @param array $server
     * @return ServerRequest
     */
    public static function makeGetRequest(
        string $uri = '',
        array $headers = [],
        array $server = []
    ): ServerRequest {
        if (empty($server)) {
            $server = $_SERVER;
        }
        if (empty($server['REQUEST_METHOD'])) {
            $server['REQUEST_METHOD'] = 'GET';
        }
        return new ServerRequest(
            'GET',
            new Uri($uri),
            $headers,
            null,
            '1.1',
            $server
        );
    }

    /**
     * @param string $uri
     * @param array $args
     * @param array $headers
     * @param array $server
     * @return ServerRequest
     */
    public static function makePostRequest(
        string $uri = '',
        array $args = [],
        array $headers = [],
        array $server = []
    ): ServerRequest {
        if (empty($server)) {
            $server = $_SERVER;
        }
        if (empty($server['REQUEST_METHOD'])) {
            $server['REQUEST_METHOD'] = 'POST';
        }
        return new ServerRequest(
            'POST',
            new Uri($uri),
            $headers,
            \http_build_query($args),
            '1.1',
            $server
        );
    }

    /**
     * @param string $uri
     * @param array $post
     * @return ResponseInterface
     * @throws \TypeError
     */
    public static function route(string $uri = '', array $post = []): ResponseInterface
    {
        $state = State::instance();
        $autoPilot = $state->autoPilot;
        if (!$autoPilot instanceof AutoPilot) {
            throw new \TypeError();
        }
        if (!empty($post)) {
            $request = static::makeGetRequest($uri);
        } else {
            $request = static::makePostRequest($uri, $post);
        }
        try {
            return $autoPilot->route($request);
        } catch (ControllerComplete $ex) {
            return $autoPilot->getLanding()->getResponseObject();
        }
    }
}
