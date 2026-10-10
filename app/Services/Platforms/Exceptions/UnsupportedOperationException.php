<?php

namespace App\Services\Platforms\Exceptions;

class UnsupportedOperationException extends PlatformException
{
    public bool $retryable = false;
}
