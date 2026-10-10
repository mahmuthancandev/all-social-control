<?php

namespace App\Services\Platforms\Exceptions;

use RuntimeException;

class PlatformException extends RuntimeException
{
    /** Tekrar denemenin anlamlı olup olmadığı. */
    public bool $retryable = true;
}
