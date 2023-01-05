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
  Error\Result as ResultException,
  Result,
  SalesforceObject,
  Test\Fixtures\Example,
  Test\Fixtures\Record,
  Test\Fixtures\Response,
  Test\Fixtures\Team,
  Test\TestCase
};

/**
 * Test cases for the Result class.
 */
class ResultTest extends TestCase {

  /**
   * Tests handling a basic Response.
   *
   * @covers Result::fromResponse()
   * @covers Result::__construct()
   * @covers Result::first()
   * @covers Result::getIterator()
   * @covers Result::parseObjects()
   */
  public function testFromOkResponse() : void {
    $bob = Record::fromName("bob");
    $result = Result::fromResponse(Response::fromQueriedRecords(null, $bob));
    foreach ($result as $object) {
      $this->assertInstanceOf(SalesforceObject::class, $object);
    }

    $first = $result->first();
    $this->assertInstanceOf(SalesforceObject::class, $first);
    $this->assertEquals($bob["Id"], $first->Id);
    $this->assertEquals($bob["Name"], $first->Name);
  }

  /** Tests handling a Response with no content. */
  public function testFromNoContentResponse() : void {
    $this->assertEmpty(Result::fromResponse(Response::fromName("no content"))->toArray());
  }

  /** Tests handling a Response with an unexpected status code. */
  public function testFromUnexpectedResponse() : void {
    $this->expectUncaught(ResultException::create(ResultException::UNPARSABLE_RESPONSE));
    // @phan-suppress-next-line PhanNoopNew
    Result::fromResponse(Response::fromName("internal server error"));
  }

  /** Tests handling mapping of record types to appropriate SlaesforceObject subclasses. */
  public function testWithObjectMap() : void {
    $bob = Record::fromName("bob");
    $result = Result::fromResponse(
      Response::fromQueriedRecords(null, $bob),
      [Example::TYPE => Example::class]
    );

    $first = $result->first();
    $this->assertInstanceOf(Example::class, $first);
    $this->assertEquals($bob["Id"], $first->Id);
    $this->assertEquals($bob["Name"], $first->Name);
  }

  /** Tests handling paginated Responses with a $more callback. */
  public function testWithMore() : void {
    $bob = Record::fromName("bob");
    $next = Response::nextRecordsUrl(1);
    $linda = Record::fromName("linda");

    $map = [Example::TYPE => Example::class];
    $result = Result::fromResponse(
      Response::fromQueriedRecords($next, $bob),
      $map,
      function (string $url) use ($next, $linda, $map) : Result {
        $this->assertEquals($next, $url);
        return Result::fromResponse(Response::fromQueriedRecords(null, $linda), $map);
      }
    );

    $first = $result->first();
    $this->assertInstanceOf(Example::class, $first);
    $this->assertEquals($bob["Id"], $first->Id);
    $this->assertEquals($bob["Name"], $first->Name);

    $more = $result->more();
    $second = $more->first();
    $this->assertInstanceOf(Example::class, $second);
    $this->assertEquals($linda["Id"], $second->Id);
    $this->assertEquals($linda["Name"], $second->Name);
  }

  /** Tests handling Responses with nested records and record lists. */
  public function testWithNestedResults() : void {
    $team = Record::fromName("team");
    $result = Result::fromResponse(
      Response::fromQueriedRecords(null, $team),
      [Example::TYPE => Example::class, Team::TYPE => Team::class]
    );

    $blue = $result->first();
    $this->assertInstanceOf(Team::class, $blue);
    $this->assertEquals($team["Id"], $blue->Id);
    $this->assertEquals($team["Name"], $blue->Name);

    $this->assertInstanceOf(Example::class, $blue->Manager);
    $this->assertEquals($team["Manager"]["Id"], $blue->Manager->Id);

    $this->assertInstanceOf(Result::class, $blue->Members);
    $members = iterator_to_array($blue->Members);
    foreach ($team["Members"]["records"] as $record) {
      $this->assertArrayHasKey($record["Id"], $members);
      $member = $members[$record["Id"]];
      $this->assertInstanceOf(Example::class, $member);
      $this->assertEquals($record["Id"], $member->Id);
    }
  }
}
