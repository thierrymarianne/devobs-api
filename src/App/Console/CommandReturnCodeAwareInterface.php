<?php

namespace App\Console;

interface CommandReturnCodeAwareInterface
{
    public const RETURN_STATUS_SUCCESS = 0;

    public const RETURN_STATUS_FAILURE = 1;
}
