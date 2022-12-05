<?php
/**
 * @package Nexcess/Salesforce
 * @subpackage Tests
 * @author Nexcess.net <nocworx@nexcess.net>
 * @copyright 2021 LiquidWeb Inc.
 * @license MIT
 */

namespace Nexcess\Salesforce\Test;

use Closure,
  SplObjectStorage as ObjectMap;

use Nexcess\Salesforce\ {
  Client,
  Test\TestCase
};

use GuzzleHttp\ {
  Client as HttpClient,
  Exception\RequestException,
  HandlerStack,
  Middleware,
  Promise\Promise,
  Psr7\Request as HttpRequest,
  Psr7\Response as HttpResponse
};

/** Base class for tests that involve Salesforce Api calls. */
abstract class SalesforceApiTestCase extends TestCase {

  /** @var ObjectMap List of staged Responses and Request expectations. */
  protected ObjectMap $stagedResponses;

  /**
   * Compares a Request to staged Responses and gets a matching Response and request expectations.
   *
   * @param HttpRequest $request The Request to dispatch
   * @throws RequestException If no matching Response is staged
   * @return array A matching Response and request expectations
   */
  protected function dispatchRequest(HttpRequest $request) : array {
    foreach ($this->stagedResponses() as $response => [$matcher, $expectations]) {
      if ($matcher($request)) {
        return [$response, $expectations];
      }
    }

    throw new RequestException(
      "no Response staged for {$request->getMethod()} {$request->getUri()->getPath()}",
      $request
    );
  }

  /**
   * Builds a new Client with a mock http handler.
   * This also resets any existing handler / stack and discards staged Responses and expectations.
   *
   * @return Client Api Client for testing
   */
  protected function newClient() : Client {
    $this->stagedResponses = new ObjectMap();
    $response = null;
    $stack = HandlerStack::create(function () use (&$response) : Promise {
      $promise = new Promise(function () use (&$promise, &$response) { $promise->resolve($response); });
      return $promise;
    });
    $stack->push(
      Middleware::mapRequest(function (HttpRequest $request) use (&$response) : Promise {
        [$response, $expectations] = $this->dispatchRequest($request);
        foreach ($expectations as $expectation) {
          $expectation(clone $request);
        }

        return new Promise(fn () => $request);
      })
    );

    return new Client(new HttpClient(["handler" => $stack, "http_errors" => false]));
  }

  /**
   * Makes a Response Matcher that compares against an example Request.
   *
   * @param HttpRequest $example The example to compare against
   * @return Closure A Response Matcher function
   */
  protected static function responseMatcher(HttpRequest $example) : Closure {
    // @phan-suppress-next-line PhanUnreferencedClosure
    return function (HttpRequest $request) use ($example) : bool {
      if (
        $example->getMethod() !== $request->getMethod() ||
        $example->getUri()->getPath() !== $request->getUri()->getPath()
      ) {
        return false;
      }

      parse_str($request->getUri()->getQuery(), $requestQueryParams);
      parse_str($example->getUri()->getQuery(), $exampleQueryParams);
      foreach ($exampleQueryParams as $key => $value) {
        if ($requestQueryParams[$key] ?? null !== $value) {
          return false;
        }
      }

      return true;
    };
  }

  /**
   * Gets each staged response and its matcher function and request expectations.
   *
   * @return iterable
   */
  protected function stagedResponses() : iterable {
    foreach ($this->stagedResponses as $response) {
      yield $response => $this->stagedResponses->offsetGet($response);
    }
  }

  /**
   * Sets expectations and the mock response for the test HttpClient's next request.
   *
   * @param Closure|null $matcher Callback that matches a Request (use null for default)
   * @param HttpResponse $response The Response to serve for the matching Request
   * @param Closure ...$expectations Callbacks that run an assertion: void (Request $request)
   */
  protected function stageResponseAndRequestExpectations(
    ? Closure $matcher,
    HttpResponse $response,
    Closure ...$expectations
  ) : void {
    // @phan-suppress-next-line PhanUnreferencedClosure
    $this->stagedResponses->attach($response, [$matcher ?? fn () => true, $expectations]);
  }
}
