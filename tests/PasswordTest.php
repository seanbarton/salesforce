<?php

declare(strict_types=1);

namespace Tests;

use GuzzleHttp\Client as GuzzleClient;
use LyonStahl\Salesforce\Authenticator\Password;
use LyonStahl\Salesforce\Exceptions\AuthException;
use PHPUnit\Framework\TestCase;

class PasswordTest extends TestCase
{
    public function testCreate(): void
    {
        $authenticator = Password::create();
        $this->assertInstanceOf(Password::class, $authenticator);
    }

    public function testAuthenticate(): void
    {
        // Replace with your Salesforce credentials and other required parameters
        $parameters = [
            'client_id' => 'your_client_id',
            'client_secret' => 'your_client_secret',
            'username' => 'your_username',
            'password' => 'your_password',
        ];

        $authenticator = new Password();

        // This test assumes that the authentication will succeed.
        // You may need to adjust the parameters for your specific Salesforce setup.
        $httpClient = $authenticator->authenticate($parameters);
        $this->assertInstanceOf(GuzzleClient::class, $httpClient);
    }

    public function testMissingParameters(): void
    {
        $authenticator = new Password();

        $this->expectException(AuthException::class);
        $this->expectExceptionCode(AuthException::MISSING);

        // Missing 'client_id' parameter intentionally to trigger the exception
        $parameters = [
            'client_secret' => 'your_client_secret',
            'username' => 'your_username',
            'password' => 'your_password',
        ];

        $authenticator->authenticate($parameters);
    }

    public function testFailedAuthentication(): void
    {
        // Replace with invalid Salesforce credentials
        $parameters = [
            'client_id' => 'invalid_client_id',
            'client_secret' => 'invalid_client_secret',
            'username' => 'invalid_username',
            'password' => 'invalid_password',
        ];

        $authenticator = new Password();

        $this->expectException(AuthException::class);
        $this->expectExceptionCode(AuthException::FAILED);

        $authenticator->authenticate($parameters);
    }
}
