<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ProductKey;
use Illuminate\Support\Facades\DB;

class SoftwareAccessService
{
    public function activateForOrder(Order $order): ProductKey
    {
        if (! $order->product->isSoftware()) {
            throw new \RuntimeException('Product is not a software product.');
        }

        return DB::transaction(function () use ($order) {
            $key = ProductKey::where('product_id', $order->product_id)
                ->where('status', 'available')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $key) {
                throw new \RuntimeException('no available product key');
            }

            $key->update([
                'status' => 'sold',
                'order_id' => $order->id,
                'sold_at' => now(),
            ]);

            $order->update([
                'software_access_expires_at' => now()->addMinutes(config('software.access_minutes', 20)),
            ]);

            return $key;
        });
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