<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Minimal Web Push sender (RFC 8030 + VAPID, RFC 8292). No library: pushes
 * carry no payload, so nothing has to be encrypted. The service worker
 * (public/call-sw.js) wakes up on the push and asks the server what is ringing.
 *
 * The VAPID key pair comes from WEBPUSH_PUBLIC_KEY / WEBPUSH_PRIVATE_KEY, or
 * is created once in storage/app/webpush/vapid.json. Changing it breaks every
 * registered browser (they must register again).
 */
class WebPushService
{
    private ?array $keys = null;

    public function isConfigured(): bool
    {
        return (bool) config('services.webpush.enabled', true) && rescue(fn () => $this->keys() !== [], false, false);
    }

    /** The application server key browsers subscribe with (base64url, uncompressed P-256 point). */
    public function publicKey(): ?string
    {
        return rescue(fn () => $this->keys()['public'], null, false);
    }

    /**
     * Wake one browser. Returns the push service's HTTP status (201 = queued,
     * 404/410 = the subscription is gone), or null when it could not be sent.
     */
    public function send(string $endpoint, int $ttl = 120, string $urgency = 'high'): ?int
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $parts = parse_url($endpoint);
        if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return null;
        }

        try {
            $keys = $this->keys();
            $jwt = $this->jwt('https://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : ''), $keys['private']);

            $res = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'vapid t='.$jwt.', k='.$keys['public'],
                    'TTL' => (string) max(0, $ttl),
                    'Urgency' => $urgency,
                ])
                ->withBody('', 'application/octet-stream')
                ->post($endpoint);

            if ($res->failed() && ! in_array($res->status(), [404, 410], true)) {
                Log::warning('WebPush: push refused', ['status' => $res->status(), 'body' => mb_substr($res->body(), 0, 300)]);
            }

            return $res->status();
        } catch (Throwable $e) {
            Log::warning('WebPush: send failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /** @return array{public:string,private:string} */
    private function keys(): array
    {
        if ($this->keys !== null) {
            return $this->keys;
        }

        $public = (string) config('services.webpush.public_key');
        $private = (string) config('services.webpush.private_key');
        if ($public !== '' && $private !== '') {
            return $this->keys = ['public' => $public, 'private' => str_replace('\n', "\n", $private)];
        }

        $path = storage_path('app/webpush/vapid.json');
        if (is_file($path)) {
            $json = json_decode((string) file_get_contents($path), true);
            if (is_array($json) && ! empty($json['public']) && ! empty($json['private'])) {
                return $this->keys = ['public' => $json['public'], 'private' => $json['private']];
            }
        }

        $keys = $this->generate();
        File::ensureDirectoryExists(dirname($path));
        file_put_contents($path, json_encode($keys, JSON_PRETTY_PRINT));

        return $this->keys = $keys;
    }

    /** @return array{public:string,private:string} */
    private function generate(): array
    {
        $options = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC] + $this->opensslConfig();
        $key = openssl_pkey_new($options);
        if ($key === false || ! openssl_pkey_export($key, $pem, null, $this->opensslConfig())) {
            throw new RuntimeException('WebPush: could not create the VAPID key pair ('.openssl_error_string().').');
        }

        $ec = openssl_pkey_get_details($key)['ec'];
        $point = "\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT);

        return ['public' => $this->b64($point), 'private' => $pem];
    }

    /** Windows PHP builds need their bundled openssl.cnf to create keys. */
    private function opensslConfig(): array
    {
        if (getenv('OPENSSL_CONF') || PHP_OS_FAMILY !== 'Windows') {
            return [];
        }
        $cnf = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';

        return is_file($cnf) ? ['config' => $cnf] : [];
    }

    private function jwt(string $audience, string $privatePem): string
    {
        $from = (string) config('mail.from.address');
        $subject = filter_var($from, FILTER_VALIDATE_EMAIL) ? 'mailto:'.$from : 'https://mbuniehub.com';

        $input = $this->b64(json_encode(['typ' => 'JWT', 'alg' => 'ES256']))
            .'.'.$this->b64(json_encode(['aud' => $audience, 'exp' => time() + 12 * 3600, 'sub' => $subject]));

        if (! openssl_sign($input, $der, $privatePem, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('WebPush: could not sign the VAPID token.');
        }

        return $input.'.'.$this->b64($this->derToRaw($der));
    }

    /** openssl gives an ASN.1 DER ECDSA signature; JWT ES256 wants r||s (32 bytes each). */
    private function derToRaw(string $der): string
    {
        $offset = 2 + (ord($der[1]) & 0x80 ? ord($der[1]) & 0x7F : 0); // SEQUENCE header
        $out = '';
        for ($i = 0; $i < 2; $i++) {
            $len = ord($der[$offset + 1]); // INTEGER
            $int = ltrim(substr($der, $offset + 2, $len), "\0");
            $out .= str_pad($int, 32, "\0", STR_PAD_LEFT);
            $offset += 2 + $len;
        }

        return $out;
    }

    private function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
