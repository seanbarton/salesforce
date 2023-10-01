<?php

declare(strict_types=1);

namespace seanbarton\Salesforce\Exceptions;

class ValidationException extends Exception
{
    public const VALIDATION_FAILED = 0;
    public const BAD_BYTE_LENGTH = 1;
    public const BAD_CHARACTER_LENGTH = 2;
    public const BAD_ENUM_VALUE = 3;
    public const BAD_ID = 4;
    public const VALUE_REQUIRED = 5;

    protected static $map = [
        self::VALIDATION_FAILED => ['message' => 'invalid value'],
        self::BAD_BYTE_LENGTH => ['message' => 'invalid text legnth'],
        self::BAD_CHARACTER_LENGTH => ['message' => 'invalid text legnth'],
        self::BAD_ENUM_VALUE => ['message' => 'value is not one of allowed values'],
        self::BAD_ID => ['message' => 'invalid salesforce 18-character id'],
        self::VALUE_REQUIRED => ['message' => 'value is required'],
    ];
}
