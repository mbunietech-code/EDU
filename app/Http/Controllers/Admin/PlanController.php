<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePlanRequest;
use App\Http\Requests\Admin\UpdatePlanRequest;
use App\Models\Plan;
use App\Models\Product;
use App\Services\DeletionService;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index()
    {
        $plans = Plan::with(['product'])
            ->latest()
            ->paginate(15);

        return view('admin.plans.index', compact('plans'));
    }

    public function create()
    {
        $products = Product::all();

        return view('admin.plans.create', compact('products'));
    }

    public function store(StorePlanRequest $request)
    {
        $this->authorize('create', Plan::class);

        $data = $request->validated();

        if ($data['duration_type'] === 'lifetime') {
            $data['duration_days'] = null;
        }

        $plan = Plan::create($data);

        \App\Models\ActivityLog::log(
            'plan_created',
            'Plan',
            $plan->id,
            ['name' => $plan->name, 'product_id' => $plan->product_id]
        );

        return redirect()->route('admin.plans.index')
            ->with('success', 'Plan created.');
    }

    public function edit(Plan $plan)
    {
        $products = Product::all();

        return view('admin.plans.edit', compact('plan', 'products'));
    }

    public function update(UpdatePlanRequest $request, Plan $plan)
    {
        $this->authorize('update', $plan);

        $data = $request->validated();

        if ($data['duration_type'] === 'lifetime') {
            $data['duration_days'] = null;
        }

        $plan->update($data);

        \App\Models\ActivityLog::log(
            'plan_updated',
            'Plan',
            $plan->id,
            ['name' => $plan->name, 'product_id' => $plan->product_id]
        );

        return redirect()->route('admin.plans.index')
            ->with('success', 'Plan updated.');
    }

    public function destroy(Request $request, Plan $plan, DeletionService $deletionService)
    {
        $this->authorize('delete', $plan);

        $inUse = $plan->subscriptions()->whereIn('status', ['active', 'expiring_soon', 'pending'])->exists();

        if ($inUse) {
            return back()->with('error', 'Cannot delete a plan in use by active subscriptions. Deactivate it instead.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $deletionService->delete($plan, $validated['reason']);

        return back()->with('success', 'Plan deleted.');
    }
}