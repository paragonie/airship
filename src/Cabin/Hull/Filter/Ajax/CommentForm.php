<?php
declare(strict_types=1);
namespace Airship\Cabin\Hull\Filter\Ajax;

use \Airship\Engine\Security\Filter\{
    InputFilterContainer,
    IntFilter,
    StringFilter
};

/**
 * Class CommentFilter
 * @package Airship\Cabin\Bridge\Filter\Account
 */
class CommentForm extends InputFilterContainer
{
    /**
     * NewDirFilter constructor.
     */
    public function __construct()
    {
        $this->addFilter(
                'year',
                (new StringFilter())->addCallback(function ($str): string {
                    if (!\preg_match('/^[0-9]{4}$/', $str)) {
                        throw new \TypeError();
                    }
                    return $str;
                })
            )
            ->addFilter(
                'month',
                (new StringFilter())->addCallback(function ($str): string {
                    if (!\preg_match('/^[0-9]{2}$/', $str)) {
                        throw new \TypeError();
                    }
                    return $str;
                })
            )
            ->addFilter(
                'slug',
                (new StringFilter())->addCallback(function ($str): string {
                    if (!\preg_match('/^[a-z0-9\-]+$/', $str)) {
                        throw new \TypeError();
                    }
                    return $str;
                })
            );
    }
}
