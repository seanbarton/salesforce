<?php

declare(strict_types=1);

namespace seanbarton\Salesforce\Exceptions;

use Psr\Http\Message\ResponseInterface;

class Exception extends \RuntimeException
{
    /** @var array<int,mixed> */
    protected static $map = [];

    /**
     * Creates a new Exception.
     *
     * @param array<string,mixed> $context
     */
    public static function create(int $code, array $context = []): self
    {
        $message = static::$map[$code]['message'] ?? 'unknown error';
        $message .= PHP_EOL.PHP_EOL;

        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            } elseif ($value instanceof ResponseInterface) {
                $code = (int) $value->getStatusCode();
                $value = (string) $value->getBody();
            } elseif (is_object($value)) {
                $value = get_class($value);
            } elseif (is_resource($value)) {
                $value = get_resource_type($value);
            } else {
                $value = (string) $value;
            }

            $message .= sprintf('%s: %s'.PHP_EOL, $key, $value);
        }

        return new self($message, $code);
    }
}
