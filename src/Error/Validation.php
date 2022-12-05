<?php
/**
 * @package Nexcess/Salesforce
 * @author Nexcess.net <nocworx@nexcess.net>
 * @copyright 2021 LiquidWeb Inc.
 * @license MIT
 */

namespace Nexcess\Salesforce\Error;

use Throwable;

use AT\Exceptable\Spl\RuntimeException;

use Nexcess\Salesforce\Error;

/**
 * Validation errors.
 */
class Validation extends RuntimeException implements Error {

  public const VALIDATION_FAILED = 0;
  public const BAD_BYTE_LENGTH = 1;
  public const BAD_CHARACTER_LENGTH = 2;
  public const BAD_ENUM_VALUE = 3;
  public const BAD_ID = 4;
  public const VALUE_REQUIRED = 5;


  public const INFO = [
    self::VALIDATION_FAILED => ["message" => "invalid value", "format" => "{__message__}"],
    self::BAD_BYTE_LENGTH => [
      "message" => "invalid text legnth",
      "format" => "invalid text length ({length}); must be between {minimum} and {maximum} bytes"
    ],
    self::BAD_CHARACTER_LENGTH => [
      "message" => "invalid text legnth",
      "format" => "invalid text length ({length}); must be between {minimum} and {maximum} characters"
    ],
    self::BAD_ENUM_VALUE => [
      "message" => "value is not one of allowed values",
      "format" => "value must be one of {valid_values}; '{value}' provided"
    ],
    self::BAD_ID => ["message" => "invalid salesforce 18-character id"],
    self::VALUE_REQUIRED => ["message" => "value is required"]
  ];


  /**
   * Factory: creates a Validation exception with an ad-hoc message.
   *
   * @param string $message Error message
   * @param array $context Contextual information
   * @param Throwable $previous Previous exception
   * @return Validation A new Validation error
   */
  public static function createWithMessage(
    string $message,
    array $context = [],
    Throwable $previous = null
  ) : Validation {
    $error = self::create(self::VALIDATION_FAILED, ["__message__" => $message] + $context, $previous);
    assert($error instanceof Validation);

    return $error;
  }
}
