<?php
/**
 * @package Nexcess/Salesforce
 * @author Nexcess.net <nocworx@nexcess.net>
 * @copyright 2021 LiquidWeb Inc.
 * @license MIT
 */

namespace Nexcess\Salesforce;

use Closure,
  IteratorAggregate,
  Throwable;

use Nexcess\Salesforce\ {
  Client,
  Error\Result as ResultException,
  Error\Usage as UsageException,
  SalesforceObject
};

use Psr\Http\Message\ResponseInterface as Response;

/**
 * Respresents the results of an Api call and maps records to a SaleforceObject class.
 */
class Result implements IteratorAggregate {

  /**
   * Factory: builds a new Result object from a raw Salesforce Api response.
   *
   * @param Response $response A Salesforce Api response
   * @param array $objectMap Map of salesforce type:StorageObject classnames
   * @param Closure|null $more Result ($url, $fqcn) Callback to get more results
   * @throws UsageException BAD_SFO_CLASSNAME if fqcn is not a SalesforceObject classname
   * @throws ResultException UNPARSABLE_RESPONSE if response body cannot be decoded
   * @return Result The new Result object on success
   */
  public static function from(Response $response, array $objectMap, Closure $more = null) : Result {
    return new self($response, $objectMap, $more);
  }

  /** @var string SalesforceObject classname for this Result. */
  protected string $fqcn;

  /** @var Result|null The next page of results, if any. */
  protected ? Result $moreResults = null;

  /** @var Closure|null Callback to get more results, if any. */
  protected ? Closure $moreResultsCallback = null;

  /** @var SalesforceObject[]|null Cached objects, if any. */
  protected ? array $objects = null;

  /** @var Response The Salesforce Api response. */
  protected Response $response;

  /** @var array The parsed Response body. */
  protected array $results;

  /**
   * @param Response $response A Salesforce Api response
   * @param array $objectMap Map of salesforce type:StorageObject classnames
   * @param Closure|null $more Result ($url, $fqcn) Callback to get more results
   * @throws UsageException BAD_SFO_CLASSNAME if fqcn is not a SalesforceObject classname
   * @throws ResultException UNPARSABLE_RESPONSE if response body cannot be decoded
   */
  public function __construct(Response $response, array $objectMap, Closure $more = null) {
    $this->response = $response;
    $this->moreResultsCallback = $more;
    $this->results = $this->parseResponse($this->response);

    // $this->results is typed; if it weren't an array we would have thrown on assignment above
    // @phan-suppress-next-line PhanTypeArraySuspiciousNullable
    $this->fqcn = $objectMap[$this->results['records'][0]['attributes']['type'] ?? null] ??
      SalesforceObject::class;
    if (! is_a($this->fqcn, SalesforceObject::class, true)) {
      throw UsageException::create(UsageException::BAD_SFO_CLASSNAME, ['fqcn' => $this->fqcn]);
    }
  }

  /**
   * Removes any cached objects (forcing next iteration to parse results again).
   */
  public function clearCache() : void {
    $this->objects = null;
  }

  /**
   * Gets the first Object from the result.
   * This is mainly useful with results where you expect only one record to be returned.
   *
   * @return SalesforceObject|null The first object if it exists; null otherwise
   */
  public function first() : ? SalesforceObject {
    foreach ($this as $object) {
      return $object;
    }
  }

  /** {@see https://php.net/IteratorAggregate.getIterator} */
  public function getIterator() : iterable {
    yield from isset($this->objects) ?
      $this->objects :
      $this->parseObjects();

    $more = $this->more();
    if ($more !== null) {
      yield from $more;
    }
  }

  /**
   * Gets the Id returned with this Result, if any (i.e., for create() calls).
   *
   * @return string|null 18-character Salesforce Id, if exists
   */
  public function lastId() : ? string {
    return $this->results['id'] ?? null;
  }

  /**
   * Gets more results from the Salesforce Api when paginated.
   *
   * @return Result|null The next Result object, if any
   */
  public function more() : ? Result {
    if (! isset($this->moreResultsCallback, $this->results['nextRecordsUrl'])) {
      return null;
    }

    $this->moreResults = ($this->moreResultsCallback)($this->results['nextRecordsUrl'], $this->fqcn);
    return $this->moreResults;
  }

  /**
   * Lazily builds salesforce objects from the result.
   * Caches for future use.
   *
   * @throws ResultException UNPARSABLE on failure
   * @return iterable<SalesforceObject> List of objects in the result
   */
  protected function parseObjects() : iterable {
    $fqcn = $this->fqcn;

    try {
      $objects = [];
      foreach ($this->results['records'] ?? [] as $record) {
        $object = $fqcn::createFromRecord($record);
        $objects[$object->Id] = $object;

        yield $object;
        $object = null;
      }

      $this->objects = $objects;
    } catch (Throwable $e) {
      throw ResultException::create(
        ResultException::UNPARSABLE_RECORD,
        ['record' => $record ?? null, 'fqcn' => $fqcn, 'object' => $object ?? null],
        $e
      );
    }
  }

  /**
   * Parses the Api Response and returns a normalized array of results.
   *
   * @param Response $response The Api Response to parse
   * @throws ResultException UNPARSABLE_RESPONSE if response body cannot be decoded
   * @return array Normalized response data
   */
  protected function parseResponse(Response $response) : array {
    try {
      $status = $response->getStatusCode();
      switch ($status) {
        case Client::HTTP_CREATED:
        case Client::HTTP_OK:
          $results = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);

          // normalize query/get
          if (isset($results['attributes'])) {
            return ['done' => true, 'totalSize' => 1, 'records' => [$results]];
          }
          return $results;
        case Client::HTTP_NO_CONTENT:
          return [];
        default:
          throw ResultException::create(ResultException::UNEXPECTED_STATUS_CODE, ['status' => $status]);
      }
    } catch (Throwable $e) {
      throw ResultException::create(ResultException::UNPARSABLE_RESPONSE, [], $e);
    }
  }
}