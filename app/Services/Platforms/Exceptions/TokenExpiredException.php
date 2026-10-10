<?php

namespace App\Services\Platforms\Exceptions;

class TokenExpiredException extends PlatformException
{
    public bool $retryable = false;
}
