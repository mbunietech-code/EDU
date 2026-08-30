<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\Scholarship;
use App\Models\Subscription;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Builds the items for the live scrolling ticker on the public home page.
 *
 * Content is driven by REAL activity (access unlocked, subscriptions
 * activated, new tools / scholarships / members). Manual promos from
 * config/marketing.php only pad the strip when activity is thin.
 *
 * Every activity line is anonymous — no names, emails or locations.
 */
class HomeTickerService
{
    /**
     * Cached feed — safe to call on every request / poll.
     *
     * @return list<array{icon:string,text:string,url:?string,live:bool}>
     */
    public function cachedItems(): array
    {
        $config = config('marketing.ticker', []);

        if (! ($config['enabled'] ?? true)) {
            return [];
        }

        $ttl = max(1, (int) ($config['cache_seconds'] ?? 15));

        return Cache::remember('home.ticker.items', $ttl, fn () => $this->items());
    }

    /**
     * Compute the feed fresh (no cache).
     *
     * @return list<array{icon:string,text:string,url:?string,live:bool}>
     */
    public function items(): array
    {
        $config = config('marketing.ticker', []);
        $target = max(4, (int) ($config['target_items'] ?? 10));

        $activity = $this->activityItems();

        if ($config['activity_only'] ?? false) {
            return array_slice($activity, 0, $target);
        }

        $promos = $this->promoItems();

        if (count($activity) >= $target) {
            return array_slice($activity, 0, $target);
        }

        // Pad with promos, cycling if needed, until we hit the target.
        $out = $activity;
        $i = 0;
        while (count($out) < $target && $promos !== []) {
            $out[] = $promos[$i % count($promos)];
            $i++;
        }

        return $out;
    }

    /**
     * @return list<array{icon:string,text:string,url:?string,live:bool}>
     */
    protected function promoItems(): array
    {
        return collect(config('marketing.ticker.promos', []))
            ->map(fn ($p) => [
                'icon' => (string) ($p['icon'] ?? 'star'),
                'text' => (string) ($p['text'] ?? ''),
                'url' => $p['url'] ?? null,
                'live' => false,
            ])
            ->filter(fn ($p) => $p['text'] !== '')
            ->values()
            ->all();
    }

    /**
     * Real activity, newest first.
     *
     * @return list<array{icon:string,text:string,url:?string,live:bool}>
     */
    protected function activityItems(): array
    {
        $days = (int) (config('marketing.ticker.activity_window_days') ?: 60);
        $since = now()->subDays($days);

        /** @var array<int, array{at:Carbon, icon:string, text:string, url:?string}> $events */
        $events = [];

        // Access unlocked (confirmed orders)
        $orders = Order::query()
            ->where('status', 'confirmed')
            ->where('updated_at', '>=', $since)
            ->with(['product:id,name', 'tool:id,name'])
            ->latest('updated_at')
            ->limit(12)
            ->get();
        foreach ($orders as $order) {
            $name = $order->product->name ?? $order->tool->name ?? null;
            if (! $name) {
                continue;
            }
            $events[] = [
                'at' => $order->updated_at,
                'icon' => 'unlock',
                'text' => "Someone unlocked {$name} access",
                'url' => route('public.products.index'),
            ];
        }

        // Subscriptions activated
        $subs = Subscription::query()
            ->where('status', 'active')
            ->where('created_at', '>=', $since)
            ->with(['product:id,name'])
            ->latest('created_at')
            ->limit(10)
            ->get();
        foreach ($subs as $sub) {
            $name = $sub->product->name ?? 'a plan';
            $events[] = [
                'at' => $sub->created_at,
                'icon' => 'bolt',
                'text' => "{$name} subscription just activated",
                'url' => route('public.products.index'),
            ];
        }

        // New tools published
        $tools = Tool::query()
            ->where('status', 'published')
            ->where('created_at', '>=', $since)
            ->latest('created_at')
            ->limit(6)
            ->get(['id', 'name', 'slug', 'created_at']);
        foreach ($tools as $tool) {
            $events[] = [
                'at' => $tool->created_at,
                'icon' => 'tool',
                'text' => "New tool available: {$tool->name}",
                'url' => route('user.tools.show', $tool->slug),
            ];
        }

        // New products published
        $products = Product::query()
            ->where('status', 'published')
            ->where('created_at', '>=', $since)
            ->latest('created_at')
            ->limit(6)
            ->get(['id', 'name', 'slug', 'created_at']);
        foreach ($products as $product) {
            $events[] = [
                'at' => $product->created_at,
                'icon' => 'product',
                'text' => "Now available: {$product->name}",
                'url' => route('public.products.show', $product->slug),
            ];
        }

        // New scholarships published
        $scholarships = Scholarship::query()
            ->where('status', 'published')
            ->where('created_at', '>=', $since)
            ->where(function ($q) {
                $q->whereNull('deadline')->orWhere('deadline', '>=', now()->toDateString());
            })
            ->latest('created_at')
            ->limit(6)
            ->get(['id', 'title', 'slug', 'created_at']);
        foreach ($scholarships as $scholarship) {
            $events[] = [
                'at' => $scholarship->created_at,
                'icon' => 'scholarship',
                'text' => "New scholarship: {$scholarship->title}",
                'url' => route('public.scholarships.show', $scholarship->slug),
            ];
        }

        // New members this week (aggregate, not per-user)
        $newMembers = User::query()
            ->where('is_admin', false)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
        if ($newMembers >= 3) {
            $events[] = [
                'at' => now(),
                'icon' => 'members',
                'text' => "{$newMembers} new members joined this week",
                'url' => null,
            ];
        }

        // Sort newest first, then format the relative time for display.
        usort($events, fn ($a, $b) => $b['at']->getTimestamp() <=> $a['at']->getTimestamp());

        return array_map(fn ($e) => [
            'icon' => $e['icon'],
            'text' => $e['text'].' · '.$this->ago($e['at']),
            'url' => $e['url'],
            'live' => true,
        ], $events);
    }

    protected function ago(Carbon $when): string
    {
        return $when->diffForHumans(syntax: Carbon::DIFF_RELATIVE_TO_NOW, short: true);
    }
}
