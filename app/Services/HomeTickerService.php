<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Scholarship;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Builds the items for the scrolling ticker on the public home page.
 *
 * Content = manual promos (config/marketing.php) blended with a few
 * auto-generated lines from live activity. All activity lines are
 * anonymous — no names, emails or locations are ever exposed.
 */
class HomeTickerService
{
    /**
     * @return list<array{icon:string,text:string,url:?string}>
     */
    public function items(): array
    {
        $config = config('marketing.ticker', []);

        if (! ($config['enabled'] ?? true)) {
            return [];
        }

        $promos = collect($config['promos'] ?? [])
            ->map(fn ($p) => [
                'icon' => (string) ($p['icon'] ?? '•'),
                'text' => (string) ($p['text'] ?? ''),
                'url' => $p['url'] ?? null,
            ])
            ->filter(fn ($p) => $p['text'] !== '')
            ->values()
            ->all();

        $activity = ($config['show_activity'] ?? true) ? $this->activityItems() : [];

        // Interleave promos and activity so the strip feels varied.
        return $this->interleave($promos, $activity);
    }

    /**
     * @return list<array{icon:string,text:string,url:?string}>
     */
    protected function activityItems(): array
    {
        $items = [];

        $recentGrants = Order::query()
            ->where('status', 'confirmed')
            ->where('updated_at', '>=', now()->subDays(45))
            ->with(['product:id,name', 'tool:id,name'])
            ->latest('updated_at')
            ->limit(6)
            ->get();

        foreach ($recentGrants as $order) {
            $name = $order->product->name ?? $order->tool->name ?? null;
            if (! $name) {
                continue;
            }
            $items[] = [
                'icon' => '✅',
                'text' => "Someone just got {$name} access · ".$this->ago($order->updated_at),
                'url' => route('public.products.index'),
            ];
        }

        $newMembers = User::where('is_admin', false)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
        if ($newMembers >= 3) {
            $items[] = [
                'icon' => '👥',
                'text' => "{$newMembers} new members joined this week",
                'url' => null,
            ];
        }

        $activeSubs = Subscription::where('status', 'active')->count();
        if ($activeSubs > 0) {
            $items[] = [
                'icon' => '⚡',
                'text' => "{$activeSubs} subscriptions active right now",
                'url' => null,
            ];
        }

        $openScholarships = Scholarship::query()
            ->published()
            ->open()
            ->orderByDesc('is_featured')
            ->limit(4)
            ->get(['id', 'title', 'slug']);

        foreach ($openScholarships as $scholarship) {
            $items[] = [
                'icon' => '🎓',
                'text' => "Now open: {$scholarship->title}",
                'url' => route('public.scholarships.show', $scholarship->slug),
            ];
        }

        return $items;
    }

    protected function ago(?Carbon $when): string
    {
        return $when ? $when->diffForHumans(syntax: Carbon::DIFF_RELATIVE_TO_NOW, short: true) : 'recently';
    }

    /**
     * Merge two lists, alternating, appending the remainder of the longer one.
     *
     * @param  list<array>  $a
     * @param  list<array>  $b
     * @return list<array>
     */
    protected function interleave(array $a, array $b): array
    {
        $out = [];
        $max = max(count($a), count($b));
        for ($i = 0; $i < $max; $i++) {
            if (isset($a[$i])) {
                $out[] = $a[$i];
            }
            if (isset($b[$i])) {
                $out[] = $b[$i];
            }
        }

        return $out;
    }
}
