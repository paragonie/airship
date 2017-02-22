<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Controller;

use Airship\Cabin\Hull\Controller\PublicFiles as HullPublicFiles;

/**
 * Class PublicFiles
 * @package Airship\Cabin\Bridge\Controller
 */
class PublicFiles extends HullPublicFiles
{
    /**
     * @var string
     */
    protected $cabin = 'Bridge';
}
