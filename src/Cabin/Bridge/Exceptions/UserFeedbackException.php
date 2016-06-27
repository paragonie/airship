<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Exceptions;

/**
 * Class UserFeedbackException
 *
 * These messages should be used
 *
 * @package Airship\Cabin\Bridge\Exceptions
 */
class UserFeedbackException extends \Exception
{
    /**
     * To allow this to be used verbatim in Twig templates, etc.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->getMessage();
    }
}
