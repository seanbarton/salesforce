<?php
/**
 * @package Nexcess/Salesforce
 * @subpackage Tests
 * @author Nexcess.net <nocworx@nexcess.net>
 * @copyright 2021 LiquidWeb Inc.
 * @license MIT
 *
 * @phan-file-suppress PhanUnreferencedPublicProperty
 */

namespace Nexcess\Salesforce\Test\Fixtures;

use Nexcess\Salesforce\ {
  Result,
  SalesforceObject,
  Test\Fixtures\Example
};

class Team extends SalesforceObject {

  public const TYPE = "Team";

  protected const UNEDITABLE_FIELDS = [
      "Manager",
      "Members",
      ...parent::UNEDITABLE_FIELDS
  ];

  public ? string $Name = null;
  public ? Example $Manager = null;
  public ? Result $Members = null;
}
