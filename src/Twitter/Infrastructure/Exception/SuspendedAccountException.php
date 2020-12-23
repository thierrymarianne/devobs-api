<?php

namespace App\Twitter\Infrastructure\Exception;

/**
 * @author Thierry Marianne <thierry.marianne@weaving-the-web.org>
 */
class SuspendedAccountException extends UnavailableResourceException
{
    public string $screenName;
    public string $twitterId;

    public static function raiseExceptionAboutSuspendedMemberHavingScreenName(
        string $screenName,
        string $twitterId,
        int $code = 0,
        \Throwable $previous = null
    ): void {
        $exception = new self(
            sprintf(
                'Member with screen name "%s" is suspended',
                $screenName
            ),
            $code,
            $previous
        );
        $exception->screenName = $screenName;
        $exception->twitterId = $twitterId;

        throw $exception;
    }
}
