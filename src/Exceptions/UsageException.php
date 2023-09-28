<?php

declare(strict_types=1);

namespace LyonStahl\Salesforce\Exceptions;

class UsageException extends Exception
{
    public const UNSUPPORTED_DATATYPE = 1;
    public const UNQUOTABLE_VALUE = 2;
    public const EMPTY_ID = 3;
    public const NO_SUCH_FIELD = 4;
    public const BAD_SFO_CLASSNAME = 5;
    public const BAD_RECORD_TYPE = 6;

    protected static $map = [
        self::UNSUPPORTED_DATATYPE => ['message' => 'Invalid or unsupported datatype.'],
        self::UNQUOTABLE_VALUE => ['message' => 'Value cannot be quoted as SOQL type.'],
        self::EMPTY_ID => ['message' => 'Object Id field is empty.'],
        self::NO_SUCH_FIELD => ['message' => 'Object contains no such field.'],
        self::BAD_SFO_CLASSNAME => ['message' => '$fqcn must be a filly qualified Record classname.'],
        self::BAD_RECORD_TYPE => ['message' => '$type must match Record TYPE.'],
    ];
}
