<?php
/**
 * @package Nexcess/Salesforce
 * @author Nexcess.net <nocworx@nexcess.net>
 * @copyright 2021 LiquidWeb Inc.
 * @license MIT
 */

namespace Nexcess\Salesforce\Error;

use AT\Exceptable\Spl\RuntimeException;

use Nexcess\Salesforce\Error;

/**
 * Errors handling Salesforce Api results.
 */
class Result extends RuntimeException implements Error {

  public const UNPARSABLE_RESPONSE = 1;
  public const UNPARSABLE_RECORD = 2;
  public const NO_TYPE = 3;
  public const UNEXPECTED_STATUS_CODE = 4;

  public const INFO = [
    self::UNPARSABLE_RESPONSE => [
      "message" => "error parsing Salesforce Api response body",
      "format" => "error parsing Salesforce Api response body: {__rootMessage__}"
    ],
    self::UNPARSABLE_RECORD => [
      "message" => "error parsing Salesforce Api resultset",
      "format" => "error parsing Salesforce Api resultset as {fqcn}: {__rootMessage__}"
    ],
    self::NO_TYPE => ["message" => "no type specified in record attributes"],
    self::UNEXPECTED_STATUS_CODE => [
      "message" => "unexpected http response status code",
      "format" => "unexpected http response status code: {status}"
    ]
  ];
}
