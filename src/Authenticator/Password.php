<?php
/**
 * @package Nexcess/Salesforce
 * @author Nexcess.net <nocworx@nexcess.net>
 * @copyright 2021 LiquidWeb Inc.
 * @license MIT
 */

namespace Nexcess\Salesforce\Authenticator;

use Nexcess\Salesforce\ {
  Authenticator\Authenticator,
  Error\Authentication as AuthenticationException
};

use GuzzleHttp\Client as HttpClient;

/**
 * Password-based Salesforce Authenticator.
 */
class Password implements Authenticator {

  /** @var array Default Http Client options. */
  protected const DEFAULT_OPTIONS = [
    "base_uri" => self::DEFAULT_LOGIN_ENDPOINT,
    "http_errors" => false
  ];

  /** @var string Default login endpoint. */
  protected const DEFAULT_LOGIN_ENDPOINT = "https://login.salesforce.com";

  /** @var array Map of Http Client options. */
  protected array $options;

  /**
   * {@inheritDoc}
   */
  public static function create(array $options = self::DEFAULT_OPTIONS) : Authenticator {
    return new self($options);
  }

  /**
   * @param array $options Map of Http Client options - @see HttpClient::__construct($options)
   *  If you pass a base_uri, it should be your Salesforce login endpoint, and not include a trailing slash.
   */
  public function __construct(array $options = []) {
    $this->options = $options + ["base_uri" => self::DEFAULT_LOGIN_ENDPOINT];
  }

  /**
   * {@inheritDoc}
   *
   * This method does not validate or process provided options.
   * Expected Authenticator parameters:
   *  - string "client_id"
   *  - string "client_secret"
   *  - string "username"
   *  - string "password"
   */
  public function authenticate(array $parameters) : HttpClient {
    $response = $this->httpClient()
      ->post("/services/oauth2/token", ["form_params" => ["grant_type" => "password"] + $parameters]);

    $auth = json_decode($response->getBody());
    if (! isset($auth->access_token, $auth->instance_url)) {
      throw AuthenticationException::create(
        AuthenticationException::FAILED,
        ["response" => $response, "parameters" => $this->obfuscate($parameters)]
      );
    }

    return $this->httpClient($auth->instance_url, $auth->access_token);
  }

  /**
   * Builds a new Http client using this Authenticator
   *
   * @throws AuthenticationException NOT_AUTHENTICATED if Authenticator has not yet succeeded
   */
  protected function httpClient(string $baseUri = null, string $accessToken = null) : HttpClient {
    $options = ["base_uri" => $baseUri ?? $this->options["base_uri"]] + $this->options;
    if (! empty($accessToken)) {
      $options["headers"]["Authorization"] = "OAuth {$accessToken}";
    }

    return new HttpClient($options);
  }

  /**
   * Obfuscates (e.g., for logging) Authenticator parameters.
   *
   * The "client_secret" and "password", if present,
   *  are hashed and can be compared to expected values using password_verify().
   *
   * @param string[] $parameters The Authenticator parameters to obfuscate
   */
  protected function obfuscate(array $parameters) : array {
    foreach (["client_secret", "password"] as $key) {
      if (isset($parameters[$key])) {
        $parameters[$key] = password_hash($parameters[$key], PASSWORD_DEFAULT);
      }
    }

    return $parameters;
  }
}
