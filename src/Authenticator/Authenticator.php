<?php

declare(strict_types=1);

namespace seanbarton\Salesforce\Authenticator;

use GuzzleHttp\Client as GuzzleClient;
use seanbarton\Salesforce\Exceptions\AuthException;

/**
 * Handles Authenticating an HTTP Client with Salesforce.
 */
interface Authenticator
{
    /**
     * Factory: builds a new Authenticator instance.
     *
     * Note, because Guzzle Clients are immutable (i.e., we cannot change the base_uri, etc.),
     *  we take default options here instead of injecting an actual Client instance.
     *
     * @param string[] $options Default options for the HTTP Client to authenticate with
     *
     * @return Authenticator The new instance
     */
    public static function create(array $options = []): Authenticator;

    /**
     * Authenticates with the Salesforce Api.
     *
     * @param string[] $parameters Authenticator parameters
     *
     * @return GuzzleClient An authenticated HTTP Client
     *
     * @throws AuthException FAILED on failure
     */
    public function authenticate(array $parameters): GuzzleClient;
}
