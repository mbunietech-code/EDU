<?php

namespace App\Services;

use App\Models\Order;

class SoftwareAccessService
{
    public function activateForOrder(Order $order): void
    {
        if (! $order->product->isSoftware()) {
            throw new \RuntimeException('Product is not a software product.');
        }

        $order->update([
            'software_access_expires_at' => now()->addMinutes(config('software.access_minutes', 20)),
        ]);
    }

    public function reopenForOrder(Order $order): void
    {
        if (! $order->product->isSoftware()) {
            throw new \RuntimeException('Product is not a software product.');
        }

        $order->update([
            'software_access_expires_at' => now()->addMinutes(config('software.access_minutes', 20)),
        ]);
    }
}