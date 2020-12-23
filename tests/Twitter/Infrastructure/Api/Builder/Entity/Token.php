<?php
declare (strict_types=1);

namespace App\Tests\Twitter\Infrastructure\Api\Builder\Entity;

use App\Twitter\Infrastructure\Api\Entity\Token as BaseToken;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;

class Token extends BaseToken
{
    public function nextFreezeEndsAt(): DateTimeInterface
    {
        return (new DateTimeImmutable(
            'now',
            new \DateTimeZone('UTC')
        ))->add(new DateInterval('PT1S'));
    }
}