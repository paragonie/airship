<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\Crew;

use ParagonIE\Ionizer\Filter\StringFilter;
use ParagonIE\Ionizer\InputFilterContainer;

/**
 * Class EditUserFilter
 * @package Airship\Cabin\Bridge\Filter\Account
 */
class EditUserFilter extends InputFilterContainer
{
    /**
     * EditUserFilter constructor.
     */
    public function __construct()
    {
        $this
            ->addFilter('username', new StringFilter())
            ->addFilter('password', new StringFilter())
            ->addFilter('email', new StringFilter())
            ->addFilter('uniqueid', new StringFilter())
            ->addFilter('display_name', new StringFilter())
            ->addFilter('real_name', new StringFilter());
    }
}
