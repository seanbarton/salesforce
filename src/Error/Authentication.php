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

class Authentication extends RuntimeException implements Error {

  public const FAILED = 1;
  public const NOT_AUTHENTICATED = 2;

  public const INFO = [
    self::FAILED => ["message" => "authentication failed"],
    self::NOT_AUTHENTICATED => ["message" => "not yet authenticated"]
  ];
}
