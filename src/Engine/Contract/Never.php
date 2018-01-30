<?php
declare(strict_types=1);
namespace Airship\Engine\Contract;

/**
 * No return
 */
final class Never
{
    public function __construct()
    {
        throw new \TypeError(__CLASS__.' is not constructable');
    }
}
