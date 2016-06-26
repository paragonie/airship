<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Landing;

use \Airship\Cabin\Hull\Landing\PublicFiles as HullPublicFiles;

/**
 * Class PublicFiles
 * @package Airship\Cabin\Bridge\Landing
 */
class PublicFiles extends HullPublicFiles
{
    /**
     * @var string
     */
    protected $cabin = 'Bridge';
}
