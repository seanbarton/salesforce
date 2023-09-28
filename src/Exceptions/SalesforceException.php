<?php

declare(strict_types=1);

namespace LyonStahl\Salesforce\Exceptions;

class SalesforceException extends Exception
{
    public const CREATE_FAILED = 1;
    public const DELETE_FAILED = 2;
    public const GET_FAILED = 3;
    public const UPDATE_FAILED = 4;
    public const UPSERT_FAILED = 5;
    public const HTTP_REQUEST_FAILED = 6;

    protected static $map = [
        self::CREATE_FAILED => ['message' => 'Failed to create record.'],
        self::DELETE_FAILED => ['message' => 'Failed to delete record.'],
        self::GET_FAILED => ['message' => 'Failed to get record.'],
        self::UPDATE_FAILED => ['message' => 'Failed to update record.'],
        self::UPSERT_FAILED => ['message' => 'Failed to upsert record.'],
        self::HTTP_REQUEST_FAILED => ['message' => 'HTTP request failed.'],
    ];
}
