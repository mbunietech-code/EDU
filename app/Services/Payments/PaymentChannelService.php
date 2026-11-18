<?php

namespace App\Services\Payments;

use App\Models\GatewayPayment;
use App\Models\Order;
use App\Models\PaymentChannel;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * The ways to pay at checkout, in the admin's priority order. Each channel is
 * switched on by itself and is offered only while its gateway is live.
 */
class PaymentChannelService
{
    /**
     * Every channel the system knows: code => [name, kind, gateway, network, description].
     * New rows start off, except channels of a gateway that was already live.
     */
    public const CATALOG = [
        'clickpesa_mpesa' => ['M-Pesa (Vodacom)', 'mobile', 'clickpesa', null, 'USSD push to the customer\'s phone'],
        'clickpesa_airtel' => ['Airtel Money', 'mobile', 'clickpesa', null, 'USSD push to the customer\'s phone'],
        'clickpesa_tigo' => ['Mixx by Yas (Tigo Pesa)', 'mobile', 'clickpesa', null, 'USSD push to the customer\'s phone'],
        'clickpesa_halopesa' => ['HaloPesa', 'mobile', 'clickpesa', null, 'USSD push to the customer\'s phone'],
        'azampay_mpesa' => ['M-Pesa (Vodacom)', 'mobile', 'azampay', 'Mpesa', 'USSD push to the customer\'s phone'],
        'azampay_tigo' => ['Mixx by Yas (Tigo Pesa)', 'mobile', 'azampay', 'Tigo', 'USSD push to the customer\'s phone'],
        'azampay_airtel' => ['Airtel Money', 'mobile', 'azampay', 'Airtel', 'USSD push to the customer\'s phone'],
        'azampay_halopesa' => ['HaloPesa', 'mobile', 'azampay', 'Halopesa', 'USSD push to the customer\'s phone'],
        'azampay_azampesa' => ['AzamPesa', 'mobile', 'azampay', 'Azampesa', 'USSD push to the customer\'s phone'],
        'pesapal_mpesa' => ['M-Pesa (Vodacom)', 'mobile', 'pesapal', null, 'PesaPal form inside checkout'],
        'pesapal_airtel' => ['Airtel Money', 'mobile', 'pesapal', null, 'PesaPal form inside checkout'],
        'pesapal_tigo' => ['Mixx by Yas (Tigo Pesa)', 'mobile', 'pesapal', null, 'PesaPal form inside checkout'],
        'pesapal_card' => ['Card (Visa / Mastercard)', 'card', 'pesapal', null, 'Card in TZS through PesaPal'],
        'pesapal_bank' => ['Bank', 'bank', 'pesapal', null, 'Bank payment, where your PesaPal account supports it'],
        'clickpesa_card' => ['Card (Visa / Mastercard)', 'card', 'clickpesa_card', null, 'Card in USD through ClickPesa (needs ClickPesa KYC)'],
        'paypal' => ['PayPal', 'wallet', 'paypal', null, 'PayPal balance or card, in USD'],
        'manual' => ['Pay manually (QR code)', 'manual', null, null, 'Scan a QR code, pay, upload proof'],
    ];

    private static ?bool $tableReady = null;

    public function __construct(protected GatewayPaymentService $gateways)
    {
    }

    /**
     * Every channel in priority order (creates missing catalog rows).
     *
     * @return Collection<int, PaymentChannel>
     */
    public function all(): Collection
    {
        if (! $this->tableReady()) {
            // Before alter 0046 is applied: behave as before channels existed.
            return collect(self::CATALOG)->keys()->values()->map(fn (string $code, int $i) => $this->fromCatalog($code, $i + 1));
        }

        $rows = PaymentChannel::orderBy('sort_order')->orderBy('id')->get();
        $missing = array_diff(array_keys(self::CATALOG), $rows->pluck('code')->all());

        if ($missing !== []) {
            $next = (int) $rows->max('sort_order');

            PaymentChannel::insertOrIgnore(collect($missing)->values()->map(function (string $code, int $i) use ($next) {
                $channel = $this->fromCatalog($code, $next + $i + 1);

                return $channel->only(['code', 'name', 'description', 'kind', 'gateway', 'network', 'sort_order'])
                    + ['enabled' => $channel->enabled, 'created_at' => now(), 'updated_at' => now()];
            })->all());

            $rows = PaymentChannel::orderBy('sort_order')->orderBy('id')->get();
        }

        // Codes dropped from the catalog are kept in the table but not offered.
        return $rows->filter(fn (PaymentChannel $channel) => isset(self::CATALOG[$channel->code]))->values();
    }

    /**
     * Channels a customer can use right now, in priority order.
     *
     * @return Collection<int, PaymentChannel>
     */
    public function available(): Collection
    {
        return $this->all()
            ->filter(fn (PaymentChannel $channel) => $channel->enabled
                && ($channel->flow() === 'manual' || $this->gateways->gateway((string) $channel->gateway)?->isEnabled()))
            ->values();
    }

    public function find(string $code): ?PaymentChannel
    {
        return $this->available()->firstWhere('code', $code);
    }

    /**
     * Push gateways that have at least one channel on, with the networks the
     * customer may pick (AzamPay needs one; ClickPesa detects it).
     *
     * @return array<string, array{gateway: MobileMoneyGateway, networks: array<string, string>}>
     */
    public function pushGateways(): array
    {
        $result = [];

        foreach ($this->available()->filter(fn (PaymentChannel $c) => $c->flow() === 'push') as $channel) {
            $gateway = $this->gateways->gateway($channel->gateway);
            $result[$channel->gateway] ??= ['gateway' => $gateway, 'networks' => []];

            if ($channel->network && $gateway->networks()) {
                $result[$channel->gateway]['networks'][$channel->network] = $gateway->networks()[$channel->network] ?? $channel->name;
            }
        }

        return $result;
    }

    public function isAvailable(string $code): bool
    {
        return $this->find($code) !== null;
    }

    /**
     * Save the admin's order and on/off switches. $rows: [[code, enabled], ...]
     * top to bottom; channels left out keep their place after these.
     */
    public function save(array $rows): void
    {
        $all = $this->all()->keyBy('code');
        $position = 0;
        $seen = [];

        foreach ($rows as $row) {
            $channel = $all->get($row['code'] ?? '');

            if (! $channel || isset($seen[$channel->code])) {
                continue;
            }

            $seen[$channel->code] = true;
            $channel->update(['enabled' => (bool) ($row['enabled'] ?? false), 'sort_order' => ++$position]);
        }

        foreach ($all as $code => $channel) {
            if (! isset($seen[$code])) {
                $channel->update(['sort_order' => ++$position]);
            }
        }
    }

    /**
     * Start a payment through a channel. Checks the phone / name the channel
     * needs; throws ValidationException (field errors) or GatewayException.
     */
    public function start(Order $order, User $user, PaymentChannel $channel, array $input, bool $fromApp = false): GatewayPayment
    {
        $gateway = $this->gateways->gateway((string) $channel->gateway);

        if ($channel->flow() === 'manual' || ! $gateway?->isEnabled()) {
            throw new GatewayException('This payment option is not available right now.');
        }

        $rawPhone = trim((string) ($input['phone'] ?? ''));
        $phone = $rawPhone !== '' ? GatewayPaymentService::normalizePhone($rawPhone) : null;

        if (($rawPhone !== '' && ! $phone) || ($channel->needsPhone() && ! $phone)) {
            throw ValidationException::withMessages(['phone' => 'Enter a valid Tanzanian mobile number, e.g. 0712 345 678.']);
        }

        $name = trim((string) ($input['name'] ?? ''));

        if ($channel->needsName() && $name === '') {
            throw ValidationException::withMessages(['name' => 'Enter the name on the card.']);
        }

        $details = array_filter(['name' => $name, 'from_app' => $fromApp, 'channel' => $channel->code]);

        return $this->gateways->start($order, $user, $gateway, $phone ?? '', $channel->network, $details);
    }

    protected function fromCatalog(string $code, int $sortOrder): PaymentChannel
    {
        [$name, $kind, $gateway, $network, $description] = self::CATALOG[$code];

        return new PaymentChannel([
            'code' => $code,
            'name' => $name,
            'description' => $description,
            'kind' => $kind,
            'gateway' => $gateway,
            'network' => $network,
            // Manual payments and gateways that were already live stay on.
            'enabled' => $kind === 'manual' || (bool) config("payments.gateways.{$gateway}.enabled"),
            'sort_order' => $sortOrder,
        ]);
    }

    protected function tableReady(): bool
    {
        return self::$tableReady ??= Schema::hasTable('payment_channels');
    }

    /**
     * Tests create the table after the first check.
     */
    public static function flushTableCheck(): void
    {
        self::$tableReady = null;
    }
}
