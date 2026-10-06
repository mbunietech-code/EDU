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
     * True when the customer pays on the provider's own page (PayPal) instead
     * of approving a push on their phone.
     */
    public function usesRedirect(): bool;

    /**
     * Fetch a fresh access token to prove the saved credentials work.
     *
     * @throws GatewayException
     */
    public function testConnection(): void;

    /**
     * Drop the cached access token (after credentials change).
     */
    public function forgetToken(): void;

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
