<?php
/**
 * @package Nexcess/Salesforce
 * @author Nexcess.net <nocworx@nexcess.net>
 * @copyright 2021 LiquidWeb Inc.
 * @license MIT
 */

namespace Nexcess\Salesforce\Authenticator;

use GuzzleHttp\Client as HttpClient;

use Nexcess\Salesforce\Error\Authentication as AuthenticationException;

/**
 * Handles Authenticating an HTTP Client with Salesforce.
 */
interface Authenticator {

  /**
   * Factory: builds a new Authenticator instance.
   *
   * Note, because Guzzle Clients are immutable (i.e., we cannot change the base_uri, etc.),
   *  we take default options here instead of injecting an actual Client instance.
   *
   * @param array $options Default options for the HTTP Client to authenticate with
   * @return Authenticator The new instance
   */
  public static function create(array $options = []) : Authenticator;

  /**
   * Authenticates with the Salesforce Api.
   *
   * @param array $parameters Authenticator parameters
   * @throws AuthenticationException FAILED on failure
   * @return HttpClient An authenticated HTTP Client
   */
  public function authenticate(array $parameters) : HttpClient;
}
