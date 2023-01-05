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
 * Errors returned from the Salesforce Api.
 */
class Salesforce extends RuntimeException implements Error {

  public const CREATE_FAILED = 1;
  public const DELETE_FAILED = 2;
  public const GET_FAILED = 3;
  public const UPDATE_FAILED = 4;
  public const UPSERT_FAILED = 5;
  public const HTTP_REQUEST_FAILED = 6;

  public const INFO = [
    self::CREATE_FAILED => [
      "message" => "failed to create record",
      "format" => "failed to create record: [{status}] {reason} {sf_error_code} {sf_error_message}"
    ],
    self::DELETE_FAILED => [
      "message" => "failed to delete record",
      "format" => "failed to delete record: [{status}] {reason} {sf_error_code} {sf_error_message}"
    ],
    self::GET_FAILED => [
      "message" => "failed to get record",
      "format" => "failed to get record: [{status}] {reason} {sf_error_code} {sf_error_message}"
    ],
    self::UPDATE_FAILED => [
      "message" => "failed to update record",
      "format" => "failed to update record: [{status}] {reason} {sf_error_code} {sf_error_message}"
    ],
    self::UPSERT_FAILED => [
      "message" => "failed to upsert record",
      "format" => "failed to upsert record: [{status}] {reason} {sf_error_code} {sf_error_message}"
    ],
    self::HTTP_REQUEST_FAILED => [
      "message" => "http request failed",
      "format" => "http request failed ({method} {path}): {__rootMessage__}"
    ]
  ];
}
