<?php

declare(strict_types=1);

namespace seanbarton\Salesforce;

use GuzzleHttp\Client as HttpClient;
use seanbarton\Salesforce\Exceptions\SalesforceException;
use seanbarton\Salesforce\Exceptions\UsageException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\StreamInterface as Stream;

class Client
{
    /** @var string String SOQL datatype */
    public const TYPE_STRING = 'string';

    /** @var string Integer SOQL datatype */
    public const TYPE_INTEGER = 'integer';

    /** @var string Float SOQL datatype */
    public const TYPE_FLOAT = 'double';

    /** @var string Decimal SOQL datatype */
    public const TYPE_DECIMAL = 'decimal';

    /** @var string Boolean SOQL datatype */
    public const TYPE_BOOLEAN = 'boolean';

    /** @var string Null SOQL datatype */
    public const TYPE_NULL = 'NULL';

    /** @var string Target Salesforce API version */
    protected $apiVersion = 'v59.0';

    /** @var string Base path for Salesforce API requests */
    protected $apiPath;

    /**
     * @var string[] Map of SOQL escape sequences
     *
     * @see https://developer.salesforce.com/docs/atlas.en-us.soql_sosl.meta/soql_sosl/sforce_api_calls_soql_select_quotedstringescapes.htm
     */
    protected const ESCAPE_MAP = [
      "\n" => '\\n',
      "\r" => '\\r',
      "\t" => '\\t',
      "\x07" => "\b",
      "\f" => '\\f',
      '"' => '\\"',
      "'" => "\\'",
      '\\' => '\\\\',
    ];

    /** @var int HTTP status code for a successful request. */
    public const HTTP_OK = 200;

    /** @var int HTTP status code for a successful request with no content. */
    public const HTTP_CREATED = 201;

    /** @var int HTTP status code for a successful request with no content. */
    public const HTTP_NO_CONTENT = 204;

    /** @var HttpClient Api client. */
    private $httpClient;

    /** @var string[] Map of salesforce object type:fully qualified Record classnames. */
    protected $objectMap = [];

    /**
     * @param HttpClient $httpClient The authenticated Http Client to use
     * @param string[]   $objectMap  Map of salesforce object type:fully qualified classnames
     * @param string     $apiVersion Target Salesforce API version
     */
    public function __construct(HttpClient $httpClient, array $objectMap = [], int $apiVersion = 59)
    {
        $this->httpClient = $httpClient;
        $this->mapObjects($objectMap);

        $this->apiVersion = 'v'.$apiVersion.'.0';
        $this->apiPath = '/services/data/'.$apiVersion;
    }

    /**
     * Creates a new Salesforce record.
     *
     * @param Record $object The object to create
     *
     * @throws SalesforceException CREATE_FAILED on failure
     */
    public function create(Record $object): Record
    {
        $response = $this->request('POST', "/sobjects/{$object->type()}", ['json' => $object->toArray()]);
        if ($response->getStatusCode() !== self::HTTP_CREATED) {
            throw SalesforceException::create(SalesforceException::CREATE_FAILED, $this->getResponseContext($response));
        }

        $created = clone $object;
        $created->Id = $this->getResultFrom($response)->lastId();

        return $created;
    }

    /**
     * Deletes an existing record in Salesforce.
     *
     * @param Record $object The object to delete
     *
     * @throws UsageException      EMPTY_ID If the $object cannot be deleted
     * @throws SalesforceException DELETE_FAILED on failure
     */
    public function delete(Record $object): Record
    {
        if (empty($object->Id)) {
            throw UsageException::create(UsageException::EMPTY_ID, ['type' => $object->type(), 'id_field' => 'Id', 'object' => $object]);
        }

        $this->deleteByExternalId($object->type(), 'Id', $object->Id);

        $deleted = clone $object;
        $deleted->Id = null;

        return $deleted;
    }

    /**
     * Deletes an existing record in Salesforce given its type and an External Id.
     *
     * @param string $type    Salesforce object type
     * @param string $idField The External Id field to look up by
     * @param string $id      18-character Salesforce Id
     *
     * @throws SalesforceException DELETE_FAILED on failure
     */
    public function deleteByExternalId(string $type, string $idField, string $id): Result
    {
        $response = $this->request('DELETE', "/sobjects/{$type}/{$idField}/{$id}");
        if ($response->getStatusCode() !== self::HTTP_NO_CONTENT) {
            throw SalesforceException::create(SalesforceException::DELETE_FAILED, $this->getResponseContext($response));
        }

        return $this->getResultFrom($response);
    }

    /**
     * Gets a Salesforce record given its type and Id.
     *
     * @param string $type Salesforce object type
     * @param string $id   18-character Salesforce Id
     *
     * @throws SalesforceException GET_FAILED on failure
     */
    public function get(string $type, string $id): Record
    {
        return $this->getByExternalId($type, 'Id', $id)->first();
    }

    /**
     * Gets a Salesforce record given its type and an External Id.
     *
     * @param string $type    Salesforce object type
     * @param string $idField The External Id field to look up by
     * @param string $id      18-character Salesforce Id
     *
     * @throws SalesforceException GET_FAILED on failure
     */
    public function getByExternalId(string $type, string $idField, string $id): Result
    {
        $response = $this->request('GET', "/sobjects/{$type}/{$idField}/{$id}");
        if ($response->getStatusCode() !== self::HTTP_OK) {
            throw SalesforceException::create(SalesforceException::GET_FAILED, $this->getResponseContext($response));
        }

        return $this->getResultFrom($response);
    }

    /**
     * Gets a new Result object for an Api Response.
     *
     * @param Response $response The Salesforce Api Response
     *
     * @return Result New Result object on success
     */
    public function getResultFrom(Response $response): Result
    {
        return Result::fromResponse(
            $response,
            $this->objectMap,
            \Closure::fromCallable([$this, 'getMoreResultsFrom'])
        );
    }

    /**
     * Maps Salesforce object types to desired php classnames.
     *
     * @param string[] $objectMap Map of salesforce object type:fully qualified classnames
     */
    public function mapObjects(array $objectMap): void
    {
        foreach ($objectMap as $type => $fqcn) {
            if (!is_a($fqcn, Record::class, true)) {
                throw UsageException::create(UsageException::BAD_SFO_CLASSNAME, ['fqcn' => $fqcn]);
            }

            $this->objectMap[$type] = $fqcn;
        }
    }

    /**
     * Performs a SOQL query.
     *
     * @param string   $template   SOQL query with optional {tokens} for parameters
     * @param string[] $parameters Parameter values (escaped prior to formatting)
     *
     * @throws UsageException      If preparing or sending the request fails
     * @throws SalesforceException If Salesforce response status is not 200 OK
     */
    public function query(string $template, array $parameters = []): Result
    {
        $response = $this->request(
            'GET',
            '/query',
            ['query' => ['q' => $this->prepare($template, $parameters)]]
        );

        if ($response->getStatusCode() !== self::HTTP_OK) {
            throw SalesforceException::create(SalesforceException::GET_FAILED, $this->getResponseContext($response));
        }

        return $this->getResultFrom($response);
    }

    /**
     * Streams the raw http response from the given path (useful for, e.g., downloading Attachments).
     *
     * @param string $path The Api path to request
     */
    public function stream(string $path): Stream
    {
        try {
            return $this->httpClient->get($path)->getBody();
        } catch (\Throwable $e) {
            throw SalesforceException::create(SalesforceException::HTTP_REQUEST_FAILED, ['method' => 'GET', 'path' => $path]);
        }
    }

    /**
     * Updates an existing record in Salesforce.
     *
     * @param Record $object The object to update from
     *
     * @throws SalesforceException UPDATE_FAILED on failure
     * @throws UsageException      NO_SUCH_FIELD If the object does not contain the given idField
     */
    public function update(
        Record $object,
        string $idField = 'Id',
        string $id = null
    ): Record {
        if (!property_exists($object, $idField)) {
            throw UsageException::create(UsageException::NO_SUCH_FIELD, ['type' => $object->type(), 'field' => $idField]);
        }

        $id ??= $object->$idField;
        if (empty($id)) {
            throw UsageException::create(UsageException::EMPTY_ID, ['type' => $object->type(), 'idField' => $idField]);
        }

        $fields = $object->toArray();
        unset($fields['Id']);
        $response = $this->request(
            'PATCH',
            "/sobjects/{$object->type()}/{$idField}/{$id}",
            ['json' => $fields]
        );

        if ($response->getStatusCode() !== self::HTTP_OK) {
            throw SalesforceException::create(SalesforceException::UPDATE_FAILED, $this->getResponseContext($response));
        }

        return $this->get($object->type(), $object->Id);
    }

    /**
     * Gets a new Result object for the next page of results.
     *
     * @param string $url Url for the page
     *
     * @return Result New Result object on success
     */
    protected function getMoreResultsFrom(string $url): Result
    {
        return $this->getResultFrom($this->httpClient->get($url));
    }

    /**
     * Gets error context from an Api response.
     *
     * @param Response $response Http response to get context from
     *
     * @return array<string,mixed> Contextual information about the response
     */
    protected function getResponseContext(Response $response): array
    {
        $payload = json_decode($response->getBody()->__toString() ?: '[]')[0] ?? [];

        return array_filter(
            [
              'response' => $response,
              'status' => $response->getStatusCode(),
              'reason' => $response->getReasonPhrase(),
              'sf_id' => $payload->id ?? null,
              'sf_errors' => $payload->errors ?? null,
              'sf_success' => $payload->success ?? null,
              'sf_created' => $payload->created ?? null,
              'sf_error_code' => $payload->errorCode ?? null,
              'sf_error_message' => isset($payload->errors) ?
                join("\n", $payload->errors) :
                ($payload->message ?? null),
            ],
            fn ($value) => isset($value)
        );
    }

    /**
     * Quotes and interpolates parameter values into an SOQL template.
     *
     * Note, SOQL statements are expected to be a single line,
     *  so newlines in the template will be removed.
     * If a newline is needed (e.g., in a string value), pass it as a parameter.
     *
     * @param string             $template   SOQL template with value {tokens}
     * @param array<mixed,mixed> $parameters Parameter token:value map
     *
     * @return string Interpolated SOQL on success
     *
     * @throws UsageException On error
     */
    protected function prepare(string $template, array $parameters): string
    {
        $replacements = ["\n" => ''];
        foreach ($parameters as $field => $value) {
            $replacements["{{$field}}"] = $this->quote($value);
        }

        return strtr($template, $replacements);
    }

    /**
     * Quotes a value for use in a SOQL query.
     *
     * @param mixed  $value The value to quote
     * @param string $type  The intended datatype
     *
     * @throws UsageException UNSUPPORTED_DATATTYPE if the given type is not one of self::TYPE_*
     * @throws UsageException UNQUOTABLE_VALUE if the value cannot be quoted as the given type
     */
    protected function quote($value, string $type = null): string
    {
        $actualType = gettype($value);
        switch ($type ?? $actualType) {
            case self::TYPE_STRING:
                if (!in_array($actualType, [self::TYPE_STRING, self::TYPE_INTEGER, self::TYPE_FLOAT])) {
                    throw UsageException::create(UsageException::UNQUOTABLE_VALUE, ['type' => $type, 'actualType' => $actualType, 'value' => $value]);
                }

                return "'".strtr($value, self::ESCAPE_MAP)."'";
            case self::TYPE_INTEGER:
                $integer = filter_var($value, FILTER_VALIDATE_INT);
                if ($integer === false) {
                    throw UsageException::create(UsageException::UNQUOTABLE_VALUE, ['type' => $type, 'actualType' => $actualType, 'value' => $value]);
                }

                return (string) $integer;
            case self::TYPE_FLOAT:
                $float = filter_var($value, FILTER_VALIDATE_FLOAT);
                if ($float === false) {
                    throw UsageException::create(UsageException::UNQUOTABLE_VALUE, ['type' => $type, 'actualType' => $actualType, 'value' => $value]);
                }

                return (string) $float;
            case self::TYPE_DECIMAL:
                if (
                    $actualType !== self::TYPE_INTEGER
                    && !($actualType === self::TYPE_FLOAT && filter_var($value, FILTER_VALIDATE_INT) !== false)
                    && !($actualType === self::TYPE_STRING && filter_var($value, FILTER_VALIDATE_FLOAT) !== false)
                ) {
                    throw UsageException::create(UsageException::UNQUOTABLE_VALUE, ['type' => $type, 'actualType' => $actualType, 'value' => $value]);
                }

                return (string) $value;
            case self::TYPE_BOOLEAN:
                if ($actualType !== self::TYPE_BOOLEAN) {
                    throw UsageException::create(UsageException::UNQUOTABLE_VALUE, ['type' => $type, 'actualType' => $actualType, 'value' => $value]);
                }

                return json_encode($value);
            case self::TYPE_NULL:
                if ($actualType !== self::TYPE_NULL) {
                    throw UsageException::create(UsageException::UNQUOTABLE_VALUE, ['type' => $type, 'actualType' => $actualType, 'value' => $value]);
                }

                return json_encode($value);
            default:
                throw UsageException::create(UsageException::UNSUPPORTED_DATATYPE, ['type' => $type]);
        }
    }

    /**
     * Performs an HTTP request and returns the Api Response.
     *
     * @param string              $method  One of GET|POST|PATCH|DELETE
     * @param string              $path    URI, without base API_PATH
     * @param array<string,mixed> $options Guzzle options
     */
    protected function request(string $method, string $path, array $options = []): Response
    {
        try {
            $path = $this->apiPath.$path;

            return $this->httpClient->request($method, $path, $options);
        } catch (\Throwable $e) {
            throw SalesforceException::create(SalesforceException::HTTP_REQUEST_FAILED, ['method' => $method, 'path' => $path, 'options' => $options]);
        }
    }
}
