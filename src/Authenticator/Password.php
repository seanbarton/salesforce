<?php

declare(strict_types=1);

namespace LyonStahl\Salesforce\Authenticator;

use GuzzleHttp\Client as GuzzleClient;
use LyonStahl\Salesforce\Exceptions\AuthException;

class Password implements Authenticator
{
    /** @var string[] */
    protected const REQUIRED_PARAMS = ['client_id', 'client_secret', 'username', 'password'];

    /** @var string */
    protected $endpoint = 'https://login.salesforce.com/';

    /** @var string */
    protected $path = '/services/oauth2/token';

    /** @var string[] */
    protected $options;

    /**
     * Creates a new Password Authenticator.
     *
     * @param string[] $options Guzzle Client options
     */
    public static function create(array $options = []): self
    {
        return new self($options);
    }

    /**
     * @param string[] $options Guzzle Client options
     */
    public function __construct(array $options = [])
    {
        // Allow overriding the default endpoint
        if (isset($options['endpoint'])) {
            $this->endpoint = $options['endpoint'];
            unset($options['endpoint']);
        }

        $defaults = [
            'base_uri' => $this->endpoint,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'http_errors' => false,
        ];

        $this->options = array_replace_recursive($defaults, $options);
    }

    /**
     * @param string[] $parameters Guzzle Client options
     */
    public function authenticate(array $parameters): GuzzleClient
    {
        $missing = array_diff(self::REQUIRED_PARAMS, array_keys($parameters));
        if (!empty($missing)) {
            throw AuthException::create(AuthException::MISSING, ['parameters' => $this->obfuscate($missing)]);
        }

        $response = $this->httpClient()->post($this->path, ['form_params' => ['grant_type' => 'password', ...$parameters]]);

        $auth = json_decode((string) $response->getBody());
        if (!isset($auth->access_token, $auth->instance_url)) {
            throw AuthException::create(AuthException::FAILED, ['response' => $response, 'parameters' => $this->obfuscate($parameters)]);
        }

        return $this->httpClient($auth->instance_url, $auth->access_token);
    }

    /**
     * Builds a new Http client using this Authenticator.
     *
     * @throws AuthException NOT_AUTHENTICATED if Authenticator has not yet succeeded
     */
    protected function httpClient(string $baseUri = null, string $accessToken = null): GuzzleClient
    {
        $options = $baseUri ? [...$this->options, $baseUri] : $this->options;
        if (!empty($accessToken)) {
            $options['headers']['Authorization'] = "OAuth {$accessToken}";
        }

        return new GuzzleClient($options);
    }

    /**
     * Obfuscates (e.g., for logging) Authenticator parameters.
     *
     * The "client_secret" and "password", if present,
     *  are hashed and can be compared to expected values using password_verify().
     *
     * @param string[] $parameters The Authenticator parameters to obfuscate
     */
    protected function obfuscate(array $parameters): array
    {
        foreach (['client_secret', 'password'] as $key) {
            if (isset($parameters[$key])) {
                $parameters[$key] = password_hash($parameters[$key], PASSWORD_DEFAULT);
            }
        }

        return $parameters;
    }
}
