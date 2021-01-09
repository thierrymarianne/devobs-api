<?php

namespace App\Twitter\Domain\Api\Accessor;

interface TwitterApiEndpointsAwareInterface
{
    public const API_ENDPOINT_RATE_LIMIT_STATUS = '/application/rate_limit_status';
}