<?php

namespace App\Services\Payments;

use App\Models\GatewayPayment;
use Illuminate\Http\Request;

interface MobileMoneyGateway
{
    public function key(): string;

    public function label(): string;

    public function isEnabled(): bool;

    /**
     * Networks the customer must choose from ([value => label]); empty when
     * the gateway detects the network from the phone number itself.
     */
    public function networks(): array;

    /**
     * Send the USSD push. Returns the provider transaction id when given.
     *
     * @throws GatewayException
     */
    public function initiate(GatewayPayment $payment): ?string;

    /**
     * Ask the provider for the latest status: 'success', 'failed', 'pending',
     * or null when the provider has no status lookup.
     *
     * @return array{status: string, reference: ?string, amount: ?float}|null
     */
    public function fetchStatus(GatewayPayment $payment): ?array;

    /**
     * Read a callback request. Returns the external id it refers to and, when
     * the callback can be trusted on its own, the result.
     *
     * @return array{external_id: ?string, status: ?string, reference: ?string, amount: ?float, verify: bool}
     */
    public function parseCallback(Request $request): array;
}
