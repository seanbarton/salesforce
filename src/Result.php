<?php

declare(strict_types=1);

namespace seanbarton\Salesforce;

use seanbarton\Salesforce\Exceptions\ResultException;
use seanbarton\Salesforce\Exceptions\UsageException;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Respresents the results of an Api call and maps records to a SaleforceObject class.
 *
 * Records (including nested records) are parsed as an appropriate Record instance.
 * Nested results (lists of records) are parsed as Result instances.
 */
class Result implements \IteratorAggregate
{
    /** @var string Record classname for this Result. */
    protected $fqcn;

    /** @var Result|null The next page of results, if any. */
    protected $moreResults;

    /** @var \Closure|null Callback to get more results, if any. */
    protected $moreResultsCallback;

    /** @var Record[]|null Cached objects, if any. */
    protected $objects;

    /** @var array<string,string> Map of salesforce type:StorageObject classnames. */
    protected $objectMap = [];

    /** @var array<string,mixed> The parsed Response body. */
    protected $results;

    /**
     * @param array<string,mixed>  $results   Parsed Salesforce Api response body
     * @param array<string,string> $objectMap Map of salesforce type:StorageObject classnames
     * @param \Closure|null        $more      Result ($url, $fqcn) Callback to get more results
     *
     * @throws UsageException  BAD_SFO_CLASSNAME if fqcn is not a Record classname
     * @throws ResultException UNPARSABLE_RESPONSE if response body cannot be decoded
     */
    public function __construct(array $results, array $objectMap = [], \Closure $more = null)
    {
        $this->results = isset($results['attributes']) ?
          ['done' => true, 'totalSize' => 1, 'records' => [$results]] :
          $results;

        $this->objectMap = $objectMap;
        $this->moreResultsCallback = $more;

        $this->fqcn = $this->objectMap[$this->results['records'][0]['attributes']['type'] ?? null] ??
          Record::class;
        if (!is_a($this->fqcn, Record::class, true)) {
            throw UsageException::create(UsageException::BAD_SFO_CLASSNAME, ['fqcn' => $this->fqcn]);
        }
    }

    /**
     * Factory: builds a new Result object from a raw Salesforce Api response.
     *
     * @param Response                  $response  A Salesforce Api response
     * @param array<sting,string>|array $objectMap Map of salesforce type:StorageObject classnames
     * @param \Closure|null             $more      Result ($url, $fqcn) Callback to get more results
     *
     * @return Result The new Result object on success
     *
     * @throws UsageException  BAD_SFO_CLASSNAME if fqcn is not a Record classname
     * @throws ResultException UNPARSABLE_RESPONSE if response body cannot be decoded
     */
    public static function fromResponse(Response $response, array $objectMap = [], \Closure $more = null): Result
    {
        try {
            $status = $response->getStatusCode();
            switch ($status) {
                case Client::HTTP_CREATED:
                case Client::HTTP_OK:
                    return new self(
                        json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR),
                        $objectMap,
                        $more
                    );
                case Client::HTTP_NO_CONTENT:
                    return new self([]);
                default:
                    throw ResultException::create(ResultException::UNEXPECTED_STATUS_CODE, ['status' => $status]);
            }
        } catch (\Throwable $e) {
            throw ResultException::create(ResultException::UNPARSABLE_RESPONSE);
        }
    }

    /**
     * Removes any cached objects (forcing next iteration to parse results again).
     */
    public function clearCache(): void
    {
        $this->objects = null;
    }

    /**
     * Gets the first Object from the result.
     * This is mainly useful with results where only one record is expected to be returned.
     *
     * @return Record|null The first object if it exists; null otherwise
     */
    public function first(): ?Record
    {
        foreach ($this as $object) {
            return $object;
        }

        return null;
    }

    /** {@see https://php.net/IteratorAggregate.getIterator} */
    public function getIterator(): \Traversable
    {
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
    public function lastId(): ?string
    {
        return $this->results['id'] ?? null;
    }

    /**
     * Gets more results from the Salesforce Api when paginated.
     *
     * @return Result|null The next Result object, if any
     */
    public function more(): ?Result
    {
        if (!isset($this->moreResults)) {
            if (isset($this->moreResultsCallback, $this->results['nextRecordsUrl'])) {
                $this->moreResults = ($this->moreResultsCallback)($this->results['nextRecordsUrl'], $this->fqcn);
            }
        }

        return $this->moreResults;
    }

    /**
     * Returns all results as an array.
     * Use with caution if the result set is big.
     *
     * @return Record[] Result Id:Record map
     */
    public function toArray(): array
    {
        return iterator_to_array($this);
    }

    /**
     * Lazily builds salesforce objects from the result.
     *
     * Caches for future use - but only if all are parsed successfully
     *  (e.g., if iteration is interrupted, we don't want to end up with a partial cache).
     *
     * @return iterable<Record> List of objects in the result
     *
     * @throws ResultException UNPARSABLE on failure
     */
    protected function parseObjects(): iterable
    {
        try {
            $objects = [];
            foreach ($this->results['records'] ?? [] as $record) {
                foreach ($record as $property => $value) {
                    // nested result (object list)
                    if (isset($value['records'])) {
                        $record[$property] = new self($value, $this->objectMap, $this->moreResultsCallback);
                        continue;
                    }

                    // nested object
                    if (isset($value['attributes'])) {
                        $record[$property] = (new self($value, $this->objectMap, $this->moreResultsCallback))
                          ->first();
                    }
                }
                $object = ($this->fqcn)::fromRecord($record);
                $objects[$object->Id] = $object;

                // don't send the original - we don't want external code to modify our cache
                yield $object->Id => clone $object;
                $object = null;
            }

            $this->objects = $objects;
        } catch (\Throwable $e) {
            throw ResultException::create(ResultException::UNPARSABLE_RECORD, ['record' => $record ?? null, 'fqcn' => $this->fqcn, 'object' => $object ?? null]);
        }
    }
}
