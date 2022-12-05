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
  Test\Fixtures\Record,
  Test\Fixtures\Response,
  Test\SalesforceApiTestCase
};

use GuzzleHttp\Psr7\Request as HttpRequest;

/**
 * Test cases for the Client->update() method.
 */
class UpdateTest extends SalesforceApiTestCase {

  /**
   * Tests successful object update.
   *
   * @covers Client::get()
   * @covers Client::update()
   * @covers Client::getResultFrom()
   * @covers Client::request()
   */
  public function testUpdate() : void {
    $bob = SalesforceObject::fromRecord(Record::fromName("bob"));

    $client = $this->newClient();
    $this->stageResponseAndRequestExpectations(
      $this->responseMatcher(
        new HttpRequest("PATCH", "/services/data/v56.0/sobjects/Example/Id/{$bob->Id}")
      ),
      Response::fromName("ok"),
      function (HttpRequest $request) use ($bob) {
        $this->assertEquals("PATCH", $request->getMethod());
        $this->assertEquals(
          "/services/data/v56.0/sobjects/Example/Id/{$bob->Id}",
          $request->getUri()->getPath()
        );
        $this->assertEquals(["Name" => "Roberto"], json_decode($request->getBody()->__toString(), true));
      }
    );
    $this->stageResponseAndRequestExpectations(
      $this->responseMatcher(
        new HttpRequest("GET", "/services/data/v56.0/sobjects/Example/Id/{$bob->Id}")
      ),
      Response::fromQueriedRecords(null, Record::fromName("roberto"))
    );

    $bob->Name = "Roberto";
    $roberto = $client->update($bob);
    $this->assertInstanceOf(SalesforceObject::class, $roberto);
    $this->assertEquals($bob->Id, $roberto->Id);
    $this->assertEquals("Roberto", $roberto->Name);
  }

  /** Tests handling a non- "200 OK" response from Salesforce. */
  public function testNotUpdated() : void {
    $client = $this->newClient();
    $this->stageResponseAndRequestExpectations(null, Response::fromName("not ok"));

    $this->expectUncaught(SalesforceException::create(SalesforceException::UPDATE_FAILED));
    $client->update(new SalesforceObject("Example", ["Id" => "000000000000000000"]));
  }

  /** Tests handling attempt to update an unupdatable object (e.g., with no Id). */
  public function testUnupdatableObject() : void {
    $client = $this->newClient();

    $this->expectUncaught(UsageException::create(UsageException::EMPTY_ID));
    $client->update(new SalesforceObject("Example", []));

    $this->expectUncaught(UsageException::create(UsageException::NO_SUCH_FIELD));
    $client->update(new SalesforceObject("Example", []), "Ssn", "123-45-6789");
  }
}
