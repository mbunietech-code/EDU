<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Minimal HS256 JSON Web Token encoder / verifier — no external dependency.
 * Used for the self-hosted live classroom: LiveKit access tokens and server
 * API tokens are signed with the API secret, and LiveKit webhooks arrive with
 * an HS256 token in the Authorization header.
 */
class Jwt
{
    public const ALGORITHM = 'HS256';

    /** Accepted clock drift when checking exp / nbf, in seconds. */
    public const LEEWAY_SECONDS = 30;

    /**
     * Encode and sign a token with HMAC-SHA256. Header defaults to
     * {alg, typ:"JWT"}; $header entries are merged on top ("alg" is always
     * HS256). Every segment is base64url without padding.
     *
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $header
     *
     * @throws \InvalidArgumentException on an empty key or a payload that cannot be encoded
     */
    public static function encode(array $payload, string $key, array $header = []): string
    {
        if (trim($key) === '') {
            throw new InvalidArgumentException('The JWT signing key is empty.');
        }

        // "alg" always reflects how the token is really signed — a caller's header cannot override it.
        $header = array_merge(['alg' => self::ALGORITHM, 'typ' => 'JWT'], $header, ['alg' => self::ALGORITHM]);

        $signingInput = self::base64UrlEncode(self::json($header)).'.'.self::base64UrlEncode(self::json($payload));

        return $signingInput.'.'.self::base64UrlEncode(hash_hmac('sha256', $signingInput, $key, true));
    }

    /**
     * Verify an HS256 token and return its payload, or null when the token is
     * malformed, uses another algorithm, has a bad signature, or is expired /
     * not yet valid (± LEEWAY_SECONDS).
     *
     * @return array<string,mixed>|null
     */
    public static function decode(string $token, string $key, ?int $now = null): ?array
    {
        if (trim($key) === '') {
            return null;
        }

        $parts = explode('.', trim($token));
        if (count($parts) !== 3) {
            return null;
        }

        [$head, $body, $signature] = $parts;
        $header = json_decode((string) self::base64UrlDecode($head), true);
        $payload = json_decode((string) self::base64UrlDecode($body), true);

        // Only HS256 — never "none" or an algorithm chosen by the sender.
        if (! is_array($header) || ($header['alg'] ?? null) !== self::ALGORITHM || ! is_array($payload)) {
            return null;
        }

        $expected = self::base64UrlEncode(hash_hmac('sha256', $head.'.'.$body, $key, true));
        if (! hash_equals($expected, $signature)) {
            return null;
        }

        $now ??= time();
        if (isset($payload['exp']) && (! is_numeric($payload['exp']) || $now - self::LEEWAY_SECONDS >= (int) $payload['exp'])) {
            return null;
        }
        if (isset($payload['nbf']) && (! is_numeric($payload['nbf']) || $now + self::LEEWAY_SECONDS < (int) $payload['nbf'])) {
            return null;
        }

        return $payload;
    }

    /** RFC 7515 base64url: URL-safe alphabet, no padding. */
    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): string|false
    {
        if (preg_match('/^[A-Za-z0-9_-]*$/', $data) !== 1) {
            return false;
        }

        return base64_decode(strtr($data, '-_', '+/').str_repeat('=', (4 - strlen($data) % 4) % 4), true);
    }

    /** @param  array<string,mixed>  $segment */
    private static function json(array $segment): string
    {
        try {
            // An empty payload must still be a JSON object, never "[]".
            return json_encode($segment === [] ? new \stdClass : $segment, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidArgumentException('The JWT segment cannot be JSON-encoded: '.$e->getMessage(), 0, $e);
        }
    }
}
