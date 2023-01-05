<?php
/**
 * @package Nexcess/Salesforce
 * @subpackage Tests
 * @author Nexcess.net <nocworx@nexcess.net>
 * @copyright 2021 LiquidWeb Inc.
 * @license MIT
 *
 * @phan-file-suppress PhanUndeclaredProperty
 */

namespace Nexcess\Salesforce\Test;

use Nexcess\Salesforce\ {
  Error\Salesforce as SalesforceException,
  SalesforceObject,
  Test\Fixtures\Record,
  Test\Fixtures\Response,
  Test\SalesforceApiTestCase
};

use GuzzleHttp\Psr7\Request as HttpRequest;

/**
 * Test cases for the Client->get() method.
 */
class GetTest extends SalesforceApiTestCase {

  /**
   * Tests successful object fetch.
   *
   * @covers Client::get()
   * @covers Client::getByExternalId()
   * @covers Client::getResultFrom()
   * @covers Client::request()
   * @dataProvider getProvider
   *
   * @param array $record The raw record to get()
   */
  public function testGet(array $record) : void {
    $client = $this->newClient();
    $this->stageResponseAndRequestExpectations(
      null,
      Response::fromQueriedRecords(null, $record),
      function (HttpRequest $request) use ($record) {
        $this->assertEquals("GET", $request->getMethod());
        $this->assertEquals(
          "/services/data/v56.0/sobjects/{$record["attributes"]["type"]}/Id/{$record["Id"]}",
          $request->getUri()->getPath()
        );
      }
    );

    $object = $client->get($record["attributes"]["type"], $record["Id"]);
    $this->assertInstanceOf(SalesforceObject::class, $object);
    $this->assertEquals($record["Id"], $object->Id);
    $this->assertEquals($record["Name"], $object->Name);
  }

  public function getProvider() : array {
    return ["bob" => [Record::fromName("bob")]];
  }

  /** Tests handling a non- "200 OK" response from Salesforce. */
  public function testGetNotOk() : void {
    $client = $this->newClient();
    $this->stageResponseAndRequestExpectations(
      null,
      Response::fromName("not found")
    );

    $this->expectUncaught(SalesforceException::create(SalesforceException::GET_FAILED));
    $client->get("Example", "000000000000000000");
  }
}
