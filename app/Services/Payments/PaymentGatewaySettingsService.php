<?php

namespace App\Services\Payments;

use App\Models\Setting;
use App\Services\CredentialService;
use Illuminate\Support\Str;

/**
 * Gateway credentials managed from Admin → Payment Methods. Values saved
 * there override .env; secrets are stored encrypted.
 */
class PaymentGatewaySettingsService
{
    public const GROUP = 'payment_gateways';

    public const CALLBACK_TOKEN_KEY = 'payment_gateway.callback_token';

    /**
     * Editable fields per gateway: field => [label, type].
     * Types: toggle, mode, text, secret.
     */
    public const FIELDS = [
        'azampay' => [
            'enabled' => ['Enabled', 'toggle'],
            'environment' => ['Environment', 'mode'],
            'app_name' => ['App name', 'text'],
            'client_id' => ['Client ID', 'text'],
            'client_secret' => ['Client secret', 'secret'],
            'api_key' => ['API key (X-API-Key)', 'secret'],
        ],
        'clickpesa' => [
            'enabled' => ['Enabled', 'toggle'],
            'client_id' => ['Client ID', 'text'],
            'api_key' => ['API key', 'secret'],
            'checksum_key' => ['Checksum key (only if checksum is on)', 'secret'],
        ],
        'paypal' => [
            'enabled' => ['Enabled', 'toggle'],
            'mode' => ['Environment', 'mode'],
            'client_id' => ['Client ID', 'text'],
            'client_secret' => ['Client secret', 'secret'],
        ],
    ];

    public function __construct(protected CredentialService $credentials)
    {
    }

    /**
     * Push saved settings into config('payments.*'). Called on every boot.
     */
    public function apply(): void
    {
        $saved = Setting::where('group', self::GROUP)->pluck('value', 'key');

        foreach (self::FIELDS as $gateway => $fields) {
            foreach ($fields as $field => [, $type]) {
                $key = $this->key($gateway, $field);

                if (! $saved->has($key) || $saved->get($key) === null || $saved->get($key) === '') {
                    continue;
                }

                $value = $saved->get($key);

                $value = match ($type) {
                    'toggle' => (bool) (int) $value,
                    'secret' => $this->credentials->decryptValue($value),
                    default => $value,
                };

                if ($value !== null) {
                    config(["payments.gateways.{$gateway}.{$field}" => $value]);
                }
            }
        }

        if ($token = $this->credentials->decryptValue($saved->get(self::CALLBACK_TOKEN_KEY))) {
            config(['payments.callback_token' => $token]);
        }
    }

    /**
     * Save a gateway's settings. Blank secret fields keep the saved value.
     */
    public function save(string $gateway, array $input): void
    {
        foreach (self::FIELDS[$gateway] as $field => [, $type]) {
            $key = $this->key($gateway, $field);

            if ($type === 'toggle') {
                $this->put($key, ! empty($input[$field]) ? '1' : '0');

                continue;
            }

            $value = trim((string) ($input[$field] ?? ''));

            if ($type === 'secret') {
                if ($value !== '') {
                    $this->put($key, $this->credentials->encrypt($value));
                }

                continue;
            }

            $this->put($key, $value);
        }

        $this->apply();
    }

    /**
     * The secret used in callback URLs; created on first use.
     */
    public function callbackToken(): string
    {
        $token = (string) config('payments.callback_token');

        if ($token === '') {
            $token = $this->regenerateCallbackToken();
        }

        return $token;
    }

    public function regenerateCallbackToken(): string
    {
        $token = Str::random(48);
        $this->put(self::CALLBACK_TOKEN_KEY, $this->credentials->encrypt($token));
        config(['payments.callback_token' => $token]);

        return $token;
    }

    /**
     * Current values for the admin form. Secrets are never sent back, only
     * whether one is set.
     */
    public function formValues(string $gateway): array
    {
        $values = [];

        foreach (self::FIELDS[$gateway] as $field => [, $type]) {
            $current = config("payments.gateways.{$gateway}.{$field}");
            $values[$field] = $type === 'secret' ? filled($current) : $current;
        }

        return $values;
    }

    protected function key(string $gateway, string $field): string
    {
        return "payment_gateway.{$gateway}.{$field}";
    }

    protected function put(string $key, string $value): void
    {
        Setting::set($key, $value, 'string', self::GROUP);
    }
}
