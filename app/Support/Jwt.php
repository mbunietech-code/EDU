<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Minimal JSON Web Token encoder for the live-classroom providers — no
 * external dependency. Supports HS256 (self-hosted Jitsi token auth) and
 * RS256 (JaaS / 8x8.vc).
 */
class Jwt
{
    public const ALGORITHMS = ['HS256', 'RS256'];

    /**
     * Encode and sign a token.
     *
     * HS256 signs with hash_hmac('sha256') using $key as the shared secret;
     * RS256 signs with openssl_sign(OPENSSL_ALGO_SHA256) using $key as a PEM
     * private key. Header defaults to {alg, typ:"JWT"}; $header entries (e.g.
     * "kid") are merged on top. Every segment is base64url without padding.
     *
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $header
     *
     * @throws \InvalidArgumentException on an unsupported algorithm, an empty key or an unusable private key
     */
    public static function encode(array $payload, string $key, string $alg = 'HS256', array $header = []): string
    {
        if (! in_array($alg, self::ALGORITHMS, true)) {
            throw new InvalidArgumentException('Unsupported JWT algorithm ['.$alg.']; use '.implode(' or ', self::ALGORITHMS).'.');
        }

        if (trim($key) === '') {
            throw new InvalidArgumentException('The JWT signing key is empty.');
        }

        // "alg" always reflects how the token is really signed — a caller's header cannot override it.
        $header = array_merge(['alg' => $alg, 'typ' => 'JWT'], $header, ['alg' => $alg]);

        $signingInput = self::base64UrlEncode(self::json($header)).'.'.self::base64UrlEncode(self::json($payload));

        return $signingInput.'.'.self::base64UrlEncode(self::sign($signingInput, $key, $alg));
    }

    /** RFC 7515 base64url: URL-safe alphabet, no padding. */
    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
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

    private static function sign(string $input, string $key, string $alg): string
    {
        if ($alg === 'HS256') {
            return hash_hmac('sha256', $input, $key, true);
        }

        $privateKey = openssl_pkey_get_private($key);

        if ($privateKey === false) {
            self::clearOpensslErrors();

            throw new InvalidArgumentException('RS256 needs a readable PEM private key.');
        }

        $details = openssl_pkey_get_details($privateKey);

        if (($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA) {
            throw new InvalidArgumentException('RS256 needs an RSA private key.');
        }

        $signature = '';

        if (! openssl_sign($input, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            self::clearOpensslErrors();

            throw new InvalidArgumentException('The token could not be signed with the RS256 key.');
        }

        return $signature;
    }

    /** OpenSSL keeps an error queue per process; drain it so later calls are not misreported. */
    private static function clearOpensslErrors(): void
    {
        while (openssl_error_string() !== false) {
            // drain
        }
    }
}
