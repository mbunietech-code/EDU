<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\HomeTickerService;
use Illuminate\Http\JsonResponse;

class TickerController extends Controller
{
    /**
     * Live feed for the home page ticker. Polled by the browser every
     * few seconds; the payload is cached server-side.
     */
    public function feed(HomeTickerService $ticker): JsonResponse
    {
        $items = $ticker->cachedItems();

        return response()->json([
            'items' => $items,
            'refresh_seconds' => (int) (config('marketing.ticker.refresh_seconds') ?: 20),
            'generated_at' => now()->toIso8601String(),
        ])->header('Cache-Control', 'public, max-age=5');
    }
}
