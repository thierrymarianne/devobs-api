<?php

namespace App\Member\Exception;

class InvalidMemberException extends \Exception
{
    /**
     * @param $memberName
     * @throws InvalidMemberException
     */
    public static function guardAgainstInvalidUsername($memberName)
    {
        throw new self(
            sprintf('Member with username "%s" could not be found.', $memberName)
        );
    }
}
