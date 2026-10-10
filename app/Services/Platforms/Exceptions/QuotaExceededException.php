<?php

namespace App\Services\Platforms\Exceptions;

class QuotaExceededException extends PlatformException
{
    public bool $retryable = false;
}
