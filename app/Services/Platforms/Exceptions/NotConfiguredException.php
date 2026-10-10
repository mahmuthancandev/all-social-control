<?php

namespace App\Services\Platforms\Exceptions;

class NotConfiguredException extends PlatformException
{
    public bool $retryable = false;
}
