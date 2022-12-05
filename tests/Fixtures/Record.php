<?php
/**
 * @package Nexcess/Salesforce
 * @subpackage Tests
 * @author Nexcess.net <nocworx@nexcess.net>
 * @copyright 2021 LiquidWeb Inc.
 * @license MIT
 */

namespace Nexcess\Salesforce\Test\Fixtures;

use LogicException;

/**
 * Salesforce records, as php arrays.
 *
 * Use this as a base class to build fixtures for your own tests:
 *  - extend this class
 *  - define your own raw records, like recordname: [attributes: [type:type, url:url], property:value, ...]
 *  - use the property name "__records__" to make properties with inline records as their value,
 *     like __records__: [property:recordname, ...]
 *  - use the property name "__recordLists__" to make properties with lists of records as their value,
 *     like __recordLists__: [property:[nextpage, recordname, ...], ...]
 */
class Record {

  /** @var string Default Salesforce ID for use with Examples. */
  public const RECORD_IDS = [
    "bob" => "5003a00001IJpONAA1",
    "linda" => "5003a00001IJpONAA2",
    "paul" => "5003a00001IJpONAA3"
  ];

  /** @var array[] Raw records to build Salesforce Objects from. */
  protected const RECORDS = [
    "bob" => [
      "attributes" => [
        "type" => "Example",
        "url" => "/services/data/v56.0/sobjects/Example/" . self::RECORD_IDS["bob"]
      ],
      "Id" => self::RECORD_IDS["bob"],
      "Name" => "Bob"
    ],
    "linda" => [
      "attributes" => [
        "type" => "Example",
        "url" => "/services/data/v56.0/sobjects/Example/" . self::RECORD_IDS["linda"]
      ],
      "Id" => self::RECORD_IDS["linda"],
      "Name" => "Linda"
    ],
    "paul" => [
      "attributes" => [
        "type" => "Example",
        "url" => "/services/data/v56.0/sobjects/Example/" . self::RECORD_IDS["paul"]
      ],
      "Id" => self::RECORD_IDS["paul"],
      "Name" => "Paul"
    ],
    // bob, but edited
    "roberto" => [
      "attributes" => [
        "type" => "Example",
        "url" => "/services/data/v56.0/sobjects/Example/" . self::RECORD_IDS["bob"]
      ],
      "Id" => self::RECORD_IDS["bob"],
      "Name" => "Roberto"
    ],
    // contains an inline object and an object list
    "team" => [
      "attributes" => [
        "type" => "Team",
        "url" => "/services/data/v56.0/sobjects/Example/4003a00001IJpONAA1"
      ],
      "Id" => "4003a00001IJpONAA1",
      "Name" => "Blue",
      "Manager" => null,
      "Members" => [],
      "__records__" => ["Manager" => "bob"],
      "__recordLists__" => ["Members" => [null, "bob", "linda", "paul"]]
    ]
  ];

  /**
   * Gets a raw Salesforce record for a given scenario.
   *
   * @param string $name Name of the record to retrieve
   * @return array
   */
  public static function fromName(string $name) : array {
    if (! isset(static::RECORDS[$name])) {
      throw new LogicException("no record '{$name}' is configured");
    }

    $record = static::RECORDS[$name];

    // inline objects (nested records)
    if (isset($record["__records__"])) {
      foreach ($record["__records__"] as $property => $recordName) {
        $record[$property] = static::fromName($recordName);
      }
      unset($record["__records__"]);
    }

    // object lists (nested responses)
    if (isset($record["__recordLists__"])) {
      foreach ($record["__recordLists__"] as $property => $recordList) {
        $next = array_shift($recordList);
        $record[$property] = json_decode(
          Response::fromQueriedRecords(
            is_int($next) ? Response::nextRecordsUrl($next) : null,
            ...array_map(fn (string $recordName) => static::fromName($recordName), $recordList)
          )->getBody()->__toString(),
          true
        );
      }
      unset($record["__recordLists__"]);
    }

    return $record;
  }
}
