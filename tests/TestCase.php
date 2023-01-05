<?php
/**
 * @package Nexcess/Salesforce
 * @subpackage Tests
 * @author Nexcess.net <nocworx@nexcess.net>
 * @copyright 2021 LiquidWeb Inc.
 * @license MIT
 */

namespace Nexcess\Salesforce\Test;

use Throwable;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Base TestCase.
 */
abstract class TestCase extends PhpunitTestCase {

  /**
   * Sets expectations for an exception to be thrown, based on an example.
   *
   * @param Throwable $expected Exception the test expects to be thrown
   */
  public function expectUncaught(Throwable $expected) : void {
    $this->expectException(get_class($expected));
    $this->expectExceptionCode($expected->getCode());
  }
}
