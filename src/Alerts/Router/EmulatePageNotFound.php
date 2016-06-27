<?php
declare(strict_types=1);
namespace Airship\Alerts\Router;

/**
 * Class EmulatePageNotFound
 *
 * If you throw this from a Landing, it will initiate the route fallback process:
 *
 * 1. Check for custom pages.
 * 2. Check for redirects.
 * 3. If nothing else, serve a 404 Not Found.
 *
 * @package Airship\Alerts\Router
 */
class EmulatePageNotFound extends \Exception
{

}
