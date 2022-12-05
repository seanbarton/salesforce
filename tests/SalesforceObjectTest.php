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
  Error\Usage as UsageException,
  Error\Validation as ValidationException,
  SalesforceObject,
  Test\Fixtures\Example,
  Test\Fixtures\Record,
  Test\TestCase
};

/**
 * SalesforceObject tests.
 */
class SalesforceObjectTest extends TestCase {

  /**
   * Tests building a SalesforceObject from a record.
   *
   * @covers SalesforceObject::__construct()
   * @covers SalesforceObject::createFromRecord()
   */
  public function testCreateFromRecord() : void {
    $record = Record::fromName("bob");

    // generic SalesforceObject
    $bob = SalesforceObject::fromRecord($record);
    $this->assertInstanceOf(SalesforceObject::class, $bob);
    $this->assertEquals("Example", $bob->type());
    $this->assertEquals("5003a00001IJpONAA1", $bob->Id);
    $this->assertEquals("Bob", $bob->Name);

    // subclassed Example
    $bob = Example::fromRecord($record);
    $this->assertInstanceOf(Example::class, $bob);
    $this->assertEquals("Example", $bob->type());
    $this->assertEquals("5003a00001IJpONAA1", $bob->Id);
    $this->assertEquals("Bob", $bob->Name);

    // mismatched subclass
    $this->expectUncaught(UsageException::create(UsageException::BAD_RECORD_TYPE));
    // @phan-suppress-next-line PhanNoopNew
    WrongType::fromRecord($record);
  }

  /**
   * Tests converting a SalesforceObject to an array.
   *
   * @covers SalesforceObject::toArray()
   */
  public function testToArray() : void {
    $bob = Example::fromRecord(Record::fromName("bob"));

    // omits uneditable fields by default
    $data = $bob->toArray();
    $this->assertEquals(["Name"], array_keys($data));

    // all fields
    $data = $bob->toArray(false);
    $this->assertEquals(["Id", "Name"], array_keys($data));
  }

  /**
   * Tests handling of invalid field values.
   *
   * @covers SalesforceObject::__construct()
   * @covers SalesforceObject::setField()
   * @covers SalesforceObject::validateField()
   */
  public function testValidation() : void {
    $this->expectUncaught(ValidationException::create(ValidationException::BAD_CHARACTER_LENGTH));
    // @phan-suppress-next-line PhanNoopNew
    new Example("Example", ["Name" => "x"]);
  }
}

/** @internal */
class WrongType extends SalesforceObject {

  public const TYPE = "Nope";
}
