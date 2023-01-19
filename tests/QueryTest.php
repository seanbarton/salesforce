<?php
/**
 * @package Nexcess/Salesforce
 * @subpackage Tests
 * @author Nexcess.net <nocworx@nexcess.net>
 * @copyright 2021 LiquidWeb Inc.
 * @license MIT
 */

namespace Nexcess\Salesforce\Test;

use stdClass;

use Nexcess\Salesforce\ {
  Error\Salesforce as SalesforceException,
  Error\Usage as UsageException,
  SalesforceObject,
  Test\Fixtures\Record,
  Test\Fixtures\Response,
  Test\SalesforceApiTestCase
};

use GuzzleHttp\ {
  Psr7\Request as HttpRequest,
  Psr7\Response as HttpResponse
};

/**
 * Test cases for the Client->query() method.
 */
class QueryTest extends SalesforceApiTestCase {

  /**
   * Executes a query() with multiple pages of results and asserts the HttpRequest is formed as expected.
   *
   * @covers Client::getResultFrom()
   * @covers Client::getMoreResultsFrom()
   * @covers Client::prepare()
   * @covers Client::query()
   * @covers Client::quote()
   * @covers Client::request()
   */
  public function testPaginatedQuery() : void {
    $linda = Record::fromName("linda");
    $next = Response::nextRecordsUrl(1);
    $paul = Record::fromName("paul");

    $client = $this->newClient();
    $this->stageResponseAndRequestExpectations(
      $this->responseMatcher(new HttpRequest("GET", "/services/data/v56.0/query")),
      Response::fromQueriedRecords($next, $linda),
      function (HttpRequest $request) {
        $this->assertEquals("GET", $request->getMethod());
        $uri = $request->getUri();
        $this->assertEquals("/services/data/v56.0/query", $uri->getPath());
        parse_str($uri->getQuery(), $query);
        $this->assertEquals("SELECT * FROM Example LIMIT 100", $query["q"] ?? null);
      }
    );
    $this->stageResponseAndRequestExpectations(
      $this->responseMatcher(new HttpRequest("GET", $next)),
      Response::fromQueriedRecords(null, $paul),
      function (HttpRequest $request) use ($next) {
        $this->assertEquals("GET", $request->getMethod());
        $this->assertEquals($next, $request->getUri()->getPath());
      }
    );

    $results = iterator_to_array($client->query("SELECT * FROM Example LIMIT 100"));
    foreach ([$linda, $paul] as $record) {
      $this->assertArrayHasKey($record["Id"], $results);
      $this->assertEquals($results[$record["Id"]]->Name, $record["Name"]);
    }
  }

  /**
   * Executes a query() and asserts the Http Request is formed as expected.
   *
   * @covers Client::getResultFrom()
   * @covers Client::prepare()
   * @covers Client::query()
   * @covers Client::quote()
   * @covers Client::request()
   * @dataProvider queryProvider
   *
   * @param string $template SOQL template to pass to query()
   * @param array $parameters Parameter map to pass to query()
   * @param string $expected The (parsed) SOQL expected in the HttpRequest
   * @param HttpResponse $response The Api Response to stage
   */
  public function testQuery(
    string $template,
    array $parameters,
    string $expected,
    HttpResponse $response
  ) : void {
    $client = $this->newClient();
    $this->stageResponseAndRequestExpectations(
      null,
      $response,
      // @phan-suppress-next-line PhanUnreferencedClosure
      fn (HttpRequest $request) => $this->assertRequestHasSoql($expected, $request)
    );

    // not testing the Result class here -
    //  just sanity-verifying we got an iterable collection of object(s) from the response
    foreach ($client->query($template, $parameters) as $object) {
      $this->assertInstanceOf(SalesforceObject::class, $object);
    }
  }

  /**
   * @return iterable Argument lists for testQuery(). [
   *    string $description => [
   *      string $template,
   *      array $parameters,
   *      string $expected,
   *      HttpResponse $response
   *    ], ...
   *  ]
   */
  public function queryProvider() : iterable {
    $response = Response::fromQueriedRecords(null, Record::fromName("bob"));
    return [
      "literal query" => [
        "SELECT Id, Name FROM Example LIMIT 100",
        [],
        "SELECT Id, Name FROM Example LIMIT 100",
        $response
      ],
      "simple parameterized query" => [
        "SELECT Id, Name FROM Example WHERE Name={name} LIMIT 100",
        ["name" => "Bob"],
        "SELECT Id, Name FROM Example WHERE Name='Bob' LIMIT 100",
        $response
      ],
      "parameterized query with string escapes" => [
        "SELECT Id, Name FROM Example WHERE Name!={name} LIMIT 100",
        ["name" => "O'Brien\n\r\t\x07\f\"\\"],
        "SELECT Id, Name FROM Example WHERE Name!='O\'Brien\\n\\r\\t\\b\\f\\\"\\\\' LIMIT 100",
        $response
      ],
      "multiline query" => [
        "SELECT Id, Name\n FROM Example\n WHERE Name='Bob'\n LIMIT 100",
        [],
        "SELECT Id, Name FROM Example WHERE Name='Bob' LIMIT 100",
        $response
      ]
    ];
  }

  /** Tests handling a non- "200 OK" response from Salesforce. */
  public function testQueryNotOk() : void {
    $client = $this->newClient();
    $this->stageResponseAndRequestExpectations(
      null,
      Response::fromName("not ok")
    );

    $this->expectUncaught(SalesforceException::create(SalesforceException::GET_FAILED));
    $client->query("SELECT * FROM Example LIMIT 100");
  }

  /** Tests handling an unescapable datatype (e.g., an object). */
  public function testUnescapableParameter() : void {
    $client = $this->newClient();
    $this->expectUncaught(UsageException::create(UsageException::UNSUPPORTED_DATATYPE, ["type" => "object"]));
    $client->query("SELECT * FROM Example LIMIT 100", [new stdClass()]);
  }

  /**
   * Assertions to verify a Request contains the given SOQL query.
   *
   * @param string $soql The expected SOQL query
   * @param HttpRequest $request The actual Http Request to inspect
   */
  protected function assertRequestHasSoql(string $soql, HttpRequest $request) : void {
    $this->assertEquals("GET", $request->getMethod());
    $uri = $request->getUri();
    $this->assertEquals("/services/data/v56.0/query", $uri->getPath());
    parse_str($uri->getQuery(), $query);
    $this->assertEquals($soql, $query["q"] ?? null);
  }
}
