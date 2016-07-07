<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Landing;

use Airship\Cabin\Hull\Landing\CustomPages as HullCustomPages;

/**
 * Class CustomPages
 *
 * See the Hull class.
 *
 * @package Airship\Cabin\Bridge\Landing
 */
class CustomPages extends HullCustomPages
{
    /**
     * @var string
     */
    protected $cabin = 'Bridge';
}
