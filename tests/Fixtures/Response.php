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

use GuzzleHttp\Psr7\Response as HttpResponse;
/**
 * Salesforce Api responses, as PSR-7 Response objects.
 *
 * Use this as a base class to build fixtures for your own tests:
 *  - extend this class
 *  - define your own raw responses, like responsename: [status, body, headers]
 */
class Response {

  /**
   * @var array[] Map of response scenarios. [
   *    string $name => [
   *      int $status
   *      ? string $body
   *      ? array $headers
   *    ], ...
   *  ]
   */
  protected const RESPONSES = [
    "create bob" => [
      201,
      <<< JSON
      {
        "id": "5003a00001IJpONAA1",
        "errors": [],
        "success": true
      }
      JSON
    ],
    "internal server error" => [500],
    "ok" => [200],
    "no content" => [204],
    "not found" => [404],
    "not ok" => [400]
  ];

  /**
   * Builds an example api response based on the named scenario.
   *
   * @param string $name Name for response scenario to use
   * @throws LogicException If named scenario is not defined
   * @return HttpResponse
   */
  public static function fromName(string $name) : HttpResponse {
    if (! isset(static::RESPONSES[$name])) {
      throw new LogicException("no response '{$name}' is configured");
    }

    return static::build(...static::RESPONSES[$name]);
  }

  /**
   * Builds an example api response for a SOQL query.
   *
   * @param string $next A "nextRecordsUrl" to emulate pagination (null for single-page responses)
   * @param array ...$records Record(s) to include in response
   * @return HttpResponse
   */
  public static function fromQueriedRecords(? string $next, array ...$records) : HttpResponse {
    $body = ["totalSize" => 0, "done" => true, "records" => []];
    if (isset($next)) {
      $body["nextRecordsUrl"] = $next;
    }

    foreach ($records as $record) {
      $body["records"][] = $record;
    }
    $body["totalSize"] = count($body["records"]);

    return static::build(200, json_encode($body));
  }

  /**
   * Makes a "next records" URL, using the provided $page number,
   *  suitable for simulating paginated responses.
   *
   * @param int $page Arbitrary page number
   * @throws LogicException If $page number is less than 1
   * @return string A "nextRecordsUrl" url
   */
  public static function nextRecordsUrl(int $page) : string {
    if ($page < 1) {
      throw new LogicException("page numbers must be 1 or greater");
    }

    return "/services/data/v56.0/query/01gD0000002HU6KIAW-{$page}";
  }

  /**
   * Builds an Http Response object from the given details.
   *
   * @param int $status Response status
   * @param string $body Response body
   * @param array $headers Response headers as header => value pairs
   * @return HttpResponse
   */
  protected static function build(int $status, string $body = "", array $headers = []) : HttpResponse {
    return new HttpResponse($status, $headers, $body);
  }
}
