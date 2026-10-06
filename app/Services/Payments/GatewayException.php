<?php

namespace App\Services\Payments;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * A gateway refused or could not process a request. The message is safe to
 * show to the customer.
 */
class GatewayException extends RuntimeException
{
    /**
     * Build from a failed provider response. Providers return the reason as a
     * string, a list of strings (validation errors) or nested objects.
     */
    public static function fromResponse(Response $response, string $fallback): self
    {
        $reason = self::reason($response);

        return new self($reason !== '' ? Str::limit($fallback.' ('.$reason.')', 250) : $fallback);
    }

    public static function reason(Response $response): string
    {
        foreach (['message', 'error', 'errors', 'details', 'error_description'] as $key) {
            $value = $response->json($key);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }

            if (is_array($value) && $value !== []) {
                $parts = array_filter(array_map(
                    fn ($part) => is_scalar($part) ? trim((string) $part) : '',
                    Arr::flatten($value)
                ));

                if ($parts !== []) {
                    return implode('; ', array_unique($parts));
                }
            }
        }

        return '';
    }
}
