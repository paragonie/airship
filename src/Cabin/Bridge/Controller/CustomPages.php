<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Controller;

use Airship\Cabin\Hull\Controller\CustomPages as HullCustomPages;

/**
 * Class CustomPages
 *
 * See the Hull class.
 *
 * @package Airship\Cabin\Bridge\Controller
 */
class CustomPages extends HullCustomPages
{
    /**
     * @var string
     */
    protected $cabin = 'Bridge';
}
