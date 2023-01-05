<?php
/**
 * @package Nexcess/Salesforce
 * @subpackage Tests
 * @author Nexcess.net <nocworx@nexcess.net>
 * @copyright 2021 LiquidWeb Inc.
 * @license MIT
 */

namespace Nexcess\Salesforce\Test\Fixtures;

use Nexcess\Salesforce\ {
  SalesforceObject,
  Validator
};

class Example extends SalesforceObject {

  public const TYPE = "Example";

  public ? string $Name = null;

  protected function validateField(string $field) : void {
    switch ($field) {
      case "Name":
        Validator::notNull($this->Name);
        // @phan-suppress-next-line PhanTypeMismatchArgumentNullable
        Validator::characterLength($this->Name, 2, 100);
        return;
      default:
        parent::validateField($field);
        return;
    }
  }
}
