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
 * General usage errors.
 */
class Usage extends RuntimeException implements Error {

  public const UNSUPPORTED_DATATYPE = 1;
  public const UNQUOTABLE_VALUE = 2;
  public const EMPTY_ID = 3;
  public const NO_SUCH_FIELD = 4;
  public const BAD_SFO_CLASSNAME = 5;
  public const BAD_RECORD_TYPE = 6;

  public const INFO = [
    self::UNSUPPORTED_DATATYPE => [
      "message" => "invalid or unsupported datatype",
      "format" => "invalid or unsupported datatype '{type}'"
    ],
    self::UNQUOTABLE_VALUE => [
      "message" => "value cannot be quoted as SOQL type",
      "format" => "value ({actual_type}) cannot be quoted as SOQL type {type}"
    ],
    self::EMPTY_ID => [
      "message" => "object Id field is empty",
      "format" => "object ({type}) Id field '{id_field}' is empty"
    ],
    self::NO_SUCH_FIELD => [
      "message" => "object contains no such field",
      "format" => "object {type} contains no field '{field}'"
    ],
    self::BAD_SFO_CLASSNAME => [
      "message" => "\$fqcn must be a filly qualified SalesforceObject classname",
      "format" => "\$fqcn must be a filly qualified SalesforceObject classname; '{fqcn}' provided"
    ],
    self::BAD_RECORD_TYPE => [
      "message" => "\$type must match SalesforceObject TYPE",
      "message" => "\$type must be {type}; '{record_type}' provided"
    ]
  ];
}
