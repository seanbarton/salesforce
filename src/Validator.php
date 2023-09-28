<?php

declare(strict_types=1);

namespace LyonStahl\Salesforce;

use LyonStahl\Salesforce\Exceptions\ValidationException;

/**
 * Validation utility methods.
 *
 * All validation methods should accept a value as the first argument (may be typehinted)
 *  and throw a ValidationException if validation fails.
 * Validation methods should return void.
 *
 * Extend this class and add more validation codes/messages,
 *  or use ValidatationException::createWithMessage() to throw an ad-hoc exception with a cutom message.
 */
class Validator
{
    /**
     * Validates a Salesforce 18-character alphanumeric Id.
     *
     * @throws ValidationException BAD_ID if validation fails
     */
    public static function byteLength(string $value, ?int $minimum, ?int $maximum): void
    {
        $byteLength = strlen($value);
        if (
            ($minimum === null || $byteLength >= $minimum)
            && ($maximum === null || $byteLength <= $maximum)
        ) {
            return;
        }

        throw ValidationException::create(ValidationException::BAD_BYTE_LENGTH, ['value' => $value, 'length' => $byteLength, 'minimum' => $minimum, 'maximum' => $maximum]);
    }

    /**
     * Validates a Salesforce 18-character alphanumeric Id.
     *
     * @throws ValidationException BAD_CHARACTER_LENGTH if validation fails
     */
    public static function characterLength(string $value, ?int $minimum, ?int $maximum): void
    {
        $characterLength = mb_strlen($value);
        if (
            ($minimum === null || $characterLength >= $minimum)
            && ($maximum === null || $characterLength <= $maximum)
        ) {
            return;
        }

        throw ValidationException::create(ValidationException::BAD_CHARACTER_LENGTH, ['value' => $value, 'length' => $characterLength, 'minimum' => $minimum, 'maximum' => $maximum]);
    }

    /**
     * Validates a value as one of an enumerated set.
     *
     * @param string[] $validValues
     *
     * @throws ValidationException BAD_ENUM_VALUE if validation fails
     */
    public static function enum(string $value, array $validValues): void
    {
        if (in_array($value, $validValues)) {
            return;
        }

        throw ValidationException::create(ValidationException::BAD_ENUM_VALUE, ['value' => $value, 'valid_values' => join('|', $validValues)]);
    }

    /**
     * Validates a Salesforce 18-character alphanumeric Id.
     *
     * @throws ValidationException BAD_ID if validation fails
     */
    public static function Id(string $value): void
    {
        if (preg_match('(^[a-z0-9]{18}$)i', $value)) {
            return;
        }

        throw ValidationException::create(ValidationException::BAD_ID, ['value' => $value]);
    }

    public static function notNull($value): void
    {
        if (isset($value)) {
            return;
        }

        throw ValidationException::create(ValidationException::VALUE_REQUIRED);
    }
}
