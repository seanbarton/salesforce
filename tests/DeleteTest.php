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
  Error\Usage as UsageException,
  SalesforceObject,
  Test\Fixtures\Example,
  Test\Fixtures\Record,
  Test\Fixtures\Response,
  Test\SalesforceApiTestCase
};

use GuzzleHttp\Psr7\Request as HttpRequest;

/**
 * Test cases for the Client->delete() method.
 */
class DeleteTest extends SalesforceApiTestCase {

  /**
   * Tests successful object deletion.
   *
   * @covers Client::delete()
   * @covers Client::deleteByExternalId()
   * @covers Client::getResultFrom()
   * @covers Client::request()
   */
  public function testDelete() : void {
    $paul = Example::fromRecord(Record::fromName("paul"));

    $client = $this->newClient();
    $this->stageResponseAndRequestExpectations(
      null,
      Response::fromName("no content"),
      function (HttpRequest $request) use ($paul) {
        $this->assertEquals("DELETE", $request->getMethod());
        $this->assertEquals(
          "/services/data/v56.0/sobjects/Example/Id/{$paul->Id}",
          $request->getUri()->getPath()
        );
      }
    );

    $ded = $client->delete($paul);
    $this->assertInstanceOf(SalesforceObject::class, $paul);
    $this->assertEquals(null, $ded->Id);
    $this->assertEquals($paul->Name, $ded->Name);
  }

  /** Tests handling a non- "204 No Content" response from Salesforce. */
  public function testNotDeleted() : void {
    $client = $this->newClient();
    $this->stageResponseAndRequestExpectations(null, Response::fromName("ok"));

    $this->expectUncaught(SalesforceException::create(SalesforceException::DELETE_FAILED));
    $client->delete(new SalesforceObject("Example", ["Id" => "5003a00001IJpONAA1"]));
  }

  /** Tests handling attempt to delete an undeletable object (e.g., with no Id). */
  public function testUndeletableObject() : void {
    $client = $this->newClient();

    $this->expectUncaught(UsageException::create(UsageException::EMPTY_ID));
    $client->delete(new SalesforceObject("Example", []));
  }
}
