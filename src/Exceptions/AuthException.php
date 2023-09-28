<?php

declare(strict_types=1);

namespace LyonStahl\Salesforce\Exceptions;

class AuthException extends Exception
{
    public const FAILED = 1;
    public const NOT_AUTHENTICATED = 2;
    public const MISSING = 3;

    protected static $map = [
        self::FAILED => ['message' => 'Authentication failed.'],
        self::NOT_AUTHENTICATED => ['message' => 'Not authenticated.'],
        self::MISSING => ['message' => 'Missing required parameters.'],
    ];
}
