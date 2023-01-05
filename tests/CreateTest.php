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
 * Test cases for the Client->create() method.
 */
class CreateTest extends SalesforceApiTestCase {

  /**
   * Tests successful object creation.
   *
   * @covers Client::create()
   * @covers Client::getResultFrom()
   * @covers Client::request()
   */
  public function testCreate() : void {
    $data = Record::fromName("bob");
    unset($data["attributes"], $data["Id"]);

    $client = $this->newClient();
    $this->stageResponseAndRequestExpectations(
      null,
      Response::fromName("create bob"),
      function (HttpRequest $request) use ($data) {
        $this->assertEquals("POST", $request->getMethod());
        $this->assertEquals("/services/data/v56.0/sobjects/Example", $request->getUri()->getPath());
        $this->assertEquals($data, json_decode($request->getBody()->__toString(), true));
      }
    );

    $bob = $client->create(new SalesforceObject("Example", $data));
    $this->assertInstanceOf(SalesforceObject::class, $bob);
    $this->assertEquals(Record::RECORD_IDS["bob"], $bob->Id);
    $this->assertEquals($data["Name"], $bob->Name);
  }

  /** Tests handling a non- "201 Created" response from Salesforce. */
  public function testNotCreated() : void {
    $client = $this->newClient();
    $this->stageResponseAndRequestExpectations(null, Response::fromName("not ok"));

    $this->expectUncaught(SalesforceException::create(SalesforceException::CREATE_FAILED));
    $client->create(new SalesforceObject("Example", []));
  }
}
