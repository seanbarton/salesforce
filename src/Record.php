<?php

declare(strict_types=1);

namespace seanbarton\Salesforce;

use seanbarton\Salesforce\Exceptions\ResultException;
use seanbarton\Salesforce\Exceptions\UsageException;
use seanbarton\Salesforce\Exceptions\ValidationException;

/**
 * Represents a generic Salesforce object.
 *
 * This class should be extended for each particular record type used by the application.
 *
 * Define the fields applications needs as public properties.
 * Define any field(s) that must not be included in update() calls
 *  (e.g., renamed fields, nested objects or object lists)
 *  in UNEDITABLE_FIELDS.
 */
class Record
{
    /** @var string|null Salesforce record type this class requires. */
    public const TYPE = null;

    /** @var string[] Field names Salesforce does not allow to be updated. */
    protected const UNEDITABLE_FIELDS = [
      'Id',
      'LastModifiedDate',
      'IsDeleted',
      'CreatedById',
      'CreatedDate',
      'LastModifiedById',
      'SystemModstamp',
    ];

    /**
     * Builds a new Record given a raw record.
     *
     * @param array<string,mixed> $record A record from a Salesforce Api response
     *
     * @return Record A new object on success
     *
     * @throws ResultException NO_TYPE if type is missing from record
     */
    public static function fromRecord(array $record): self
    {
        $type = $record['attributes']['type'] ?? null;
        if (empty($type)) {
            throw ResultException::create(ResultException::NO_TYPE, ['record_id' => $record['Id'] ?? null]);
        }

        $metadata = array_merge([
          'CreatedById' => $record['CreatedById'] ?? null,
          'CreatedDate' => $record['CreatedDate'] ?? null,
          'SystemModstamp' => $record['SystemModstamp'] ?? null,
          'LastModifiedDate' => $record['LastModifiedDate'] ?? null,
          'LastModifiedById' => $record['LastModifiedById'] ?? null,
          'IsDeleted' => $record['IsDeleted'] ?? null,
        ], $record['attributes']);
        unset($record['attributes']);

        $object = new self($type, $record);
        $object->setMetadata($metadata);

        return $object;
    }

    /** @var string|null 18-character Salesforce global Id. */
    public $Id;

    /** @var string Object type. */
    protected $type;

    /** @var array<string,string> Salesforce record metadata. */
    protected $metadata = [];

    /** @var string[] Object field names. */
    protected $fields;

    /**
     * The constructor is not intended for direct use in code, and should not be overridden in subclasses.
     * Use/extend static::fromRecord() or other factory instead.
     *
     * @param string                  $type   Object type
     * @param array<int|string,mixed> $fields Map of object field names:values
     */
    public function __construct(string $type, array $fields)
    {
        $this->type = $type;
        if (!empty(static::TYPE) && $this->type !== static::TYPE) {
            throw UsageException::create(UsageException::BAD_RECORD_TYPE, ['type' => static::TYPE, 'record_type' => $this->type]);
        }

        // prefer defined object properties; fall back on provided field names
        $this->fields = array_keys(_getObjectFields($this));
        if (empty($this->fields) || $this->fields === ['Id']) {
            array_push($this->fields, ...array_keys($fields));
        }

        foreach ($this->fields as $field) {
            if (isset($fields[$field])) {
                $this->setField($field, $fields[$field]);
            }
        }
    }

    public function __set($field, $value)
    {
        if (in_array($field, $this->fields)) {
            $this->$field = $value;

            return;
        }

        throw UsageException::create(UsageException::NO_SUCH_FIELD, ['type' => $this->type, 'field' => $field]);
    }

    /**
     * Gets metadata about the Salesforce record (if any) this object was created from.
     *
     * @return array<string,string> Metadata (any or all fields may be null):
     *                              - $type
     *                              - $url
     *                              - $CreatedById
     *                              - $CreatedDate
     *                              - $SystemModstamp
     *                              - $LastModifiedDate
     *                              - $LastModifiedById
     *                              - $IsDeleted
     */
    public function getMetadata(): array
    {
        return array_merge([
          'type' => null,
          'url' => null,
          'CreatedById' => null,
          'CreatedDate' => null,
          'SystemModstamp' => null,
          'LastModifiedDate' => null,
          'LastModifiedById' => null,
          'IsDeleted' => null,
        ], $this->metadata);
    }

    /**
     * Sets a field with a given value.
     *
     * @param string $field Object field name
     * @param mixed  $value Field value to set
     *
     * @return self $this
     *
     * @throws UsageException      NO_SUCH_FIELD if field does not exist on the object
     * @throws ValidationException If validation fails
     */
    public function setField(string $field, $value): self
    {
        $this->$field = $value;
        $this->validateField($field);

        return $this;
    }

    /**
     * Gets the object's fields as an array.
     *
     * @param bool $forEdit Exclude fields which are unset or not editable?
     *
     * @return array<string,mixed> Map of object field names:values
     */
    public function toArray(bool $forEdit = true): array
    {
        $map = [];
        foreach ($this->fields as $field) {
            if ($forEdit && (!isset($this->$field) || in_array($field, self::UNEDITABLE_FIELDS))) {
                continue;
            }
            $map[$field] = $this->$field ?? null;
        }

        return $map;
    }

    /**
     * Gets the object type.
     */
    public function type(): string
    {
        return $this->type;
    }

    /**
     * Validates object fields,  e.g., before object creation or update.
     *
     * @param bool $forEdit Exclude fields which are unset or not editable?
     *
     * @throws ValidationException If validation fails
     */
    public function validate(bool $forEdit = true): void
    {
        foreach ($this->fields as $field) {
            if ($forEdit && (!isset($this->$field) || in_array($field, self::UNEDITABLE_FIELDS))) {
                continue;
            }

            $this->validateField($field);
        }
    }

    /**
     * Sets metadata about this object's record in Salesforce.
     *
     * @param array<string,string> $metadata Metadata to set
     */
    protected function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }

    /**
     * Validates an object field value.
     *
     * By default, only validates the 18-character Salesforce Id.
     * Extend this method to implement validation for particular Salesforce objects.
     *
     * @param string $field Object field name
     *
     * @throws ValidationException If validation fails
     */
    protected function validateField(string $field): void
    {
        switch ($field) {
            case 'Id':
                if (isset($this->Id)) {
                    Validator::Id($this->Id);
                }

                return;
            default:
                return;
        }
    }
}

/**
 * Gets the object fields (public properties) and values of a SaleforceObject.
 *
 * @return array<string,mixed> Map of object field names:values
 *
 * @internal
 */
function _getObjectFields(Record $object): array
{
    return get_object_vars($object);
}
