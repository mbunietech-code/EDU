<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Plan;
use App\Models\Product;
use Illuminate\Validation\Rule;

/**
 * Plans edited inside the product form (Admin → Products → add / edit):
 * add, change or remove a product's plans in the same save.
 */
class ProductPlanService
{
    public const MAX_PLANS = 20;

    public static function rules(): array
    {
        return [
            'plans' => ['nullable', 'array', 'max:'.self::MAX_PLANS],
            'plans.*.id' => ['nullable', 'integer'],
            'plans.*.name' => ['nullable', 'string', 'max:255'],
            'plans.*.price' => ['nullable', 'numeric', 'min:0', 'max:9999999999', 'required_with:plans.*.name'],
            'plans.*.duration_type' => ['nullable', Rule::in(['days', 'lifetime'])],
            'plans.*.duration_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'plans.*.status' => ['nullable', Rule::in(['active', 'inactive'])],
            'plans.*.remove' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Apply the form's plan rows. Rows without a name are ignored. A removed
     * plan that was ever ordered is switched off instead, to keep history.
     *
     * @return array{created: int, updated: int, removed: int, deactivated: int}
     */
    public function sync(Product $product, array $rows): array
    {
        $summary = ['created' => 0, 'updated' => 0, 'removed' => 0, 'deactivated' => 0];

        // VPN plans are copied from vpn.mbuniehub.com; edits here would be undone.
        if ($product->isVpn()) {
            return $summary;
        }

        $existing = $product->plans()->get()->keyBy('id');
        $sort = 0;

        foreach (array_slice($rows, 0, self::MAX_PLANS) as $row) {
            $plan = isset($row['id']) ? $existing->get((int) $row['id']) : null;
            $name = trim((string) ($row['name'] ?? ''));

            if (! empty($row['remove'])) {
                if ($plan) {
                    $summary[$this->remove($plan)]++;
                }

                continue;
            }

            if ($name === '') {
                continue;
            }

            $lifetime = ($row['duration_type'] ?? 'days') === 'lifetime';
            $data = [
                'name' => $name,
                'price' => (float) ($row['price'] ?? 0),
                'duration_type' => $lifetime ? 'lifetime' : 'days',
                'duration_days' => $lifetime ? null : max(1, (int) ($row['duration_days'] ?? 30)),
                'status' => ($row['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
                'sort_order' => ++$sort,
            ];

            if ($plan) {
                $plan->fill($data);

                if ($plan->isDirty()) {
                    $plan->save();
                    $summary['updated']++;
                    ActivityLog::log('plan_updated', 'Plan', $plan->id, ['name' => $plan->name, 'product_id' => $product->id]);
                }

                continue;
            }

            $plan = $product->plans()->create($data);
            $summary['created']++;
            ActivityLog::log('plan_created', 'Plan', $plan->id, ['name' => $plan->name, 'product_id' => $product->id]);
        }

        return $summary;
    }

    protected function remove(Plan $plan): string
    {
        if ($plan->orders()->exists() || $plan->subscriptions()->exists()) {
            $plan->update(['status' => 'inactive']);
            ActivityLog::log('plan_deactivated', 'Plan', $plan->id, ['name' => $plan->name, 'product_id' => $plan->product_id]);

            return 'deactivated';
        }

        ActivityLog::log('plan_deleted', 'Plan', $plan->id, ['name' => $plan->name, 'product_id' => $plan->product_id]);
        $plan->delete();

        return 'removed';
    }

    /**
     * Rows for the form: old input after a failed save, else the saved plans.
     */
    public static function formRows(?Product $product): array
    {
        if (is_array(old('plans'))) {
            return array_values(array_map(fn ($row) => [
                'id' => $row['id'] ?? null,
                'name' => (string) ($row['name'] ?? ''),
                'price' => (string) ($row['price'] ?? ''),
                'duration_type' => ($row['duration_type'] ?? 'days') === 'lifetime' ? 'lifetime' : 'days',
                'duration_days' => (string) ($row['duration_days'] ?? '30'),
                'status' => ($row['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
                'remove' => ! empty($row['remove']),
            ], old('plans')));
        }

        if (! $product) {
            return [['id' => null, 'name' => '1 month', 'price' => '', 'duration_type' => 'days', 'duration_days' => '30', 'status' => 'active', 'remove' => false]];
        }

        return $product->plans()->orderBy('sort_order')->orderBy('id')->get()->map(fn (Plan $plan) => [
            'id' => $plan->id,
            'name' => $plan->name,
            'price' => rtrim(rtrim(number_format((float) $plan->price, 2, '.', ''), '0'), '.'),
            'duration_type' => $plan->duration_type === 'lifetime' ? 'lifetime' : 'days',
            'duration_days' => (string) ($plan->duration_days ?? 30),
            'status' => $plan->status === 'inactive' ? 'inactive' : 'active',
            'remove' => false,
        ])->values()->all();
    }
}
