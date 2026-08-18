<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePlanRequest;
use App\Http\Requests\Admin\UpdatePlanRequest;
use App\Models\Plan;
use App\Models\Product;

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

    public function destroy(Plan $plan)
    {
        $this->authorize('delete', $plan);

        $plan->update(['status' => 'inactive']);

        \App\Models\ActivityLog::log(
            'plan_deactivated',
            'Plan',
            $plan->id,
            ['name' => $plan->name]
        );

        return back()->with('success', 'Plan deactivated.');
    }
}