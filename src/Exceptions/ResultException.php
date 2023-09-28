<?php

declare(strict_types=1);

namespace LyonStahl\Salesforce\Exceptions;

class ResultException extends Exception
{
    public const UNPARSABLE_RESPONSE = 1;
    public const UNPARSABLE_RECORD = 2;
    public const NO_TYPE = 3;
    public const UNEXPECTED_STATUS_CODE = 4;

    protected static $map = [
        self::UNPARSABLE_RESPONSE => ['message' => 'Error parsing Salesforce API response body.'],
        self::UNPARSABLE_RECORD => ['message' => 'Error parsing Salesforce API resultset.'],
        self::NO_TYPE => ['message' => 'No type specified in record attributes.'],
        self::UNEXPECTED_STATUS_CODE => ['message' => 'Unexpected HTTP response status code.'],
    ];
}
