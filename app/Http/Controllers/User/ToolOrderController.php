<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreToolOrderRequest;
use App\Models\Order;
use App\Models\Tool;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ToolOrderController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function create(Tool $tool)
    {
        if (! $tool->isPublished()) {
            abort(404);
        }

        return view('user.tools.order', compact('tool'));
    }

    public function store(StoreToolOrderRequest $request)
    {
        $validated = $request->validated();

        $tool = Tool::findOrFail($validated['tool_id']);

        if (! $tool->isPublished()) {
            abort(404);
        }

        $order = Order::create([
            'user_id' => auth()->id(),
            'order_number' => $this->generateOrderNumber(),
            'tool_id' => $tool->id,
            'amount' => $tool->price,
            'status' => 'pending',
        ]);

        $this->notificationService->notifyOrderCreated(auth()->user(), $order);
        \App\Models\ActivityLog::log(
            'order_created',
            'Order',
            $order->id,
            ['order_number' => $order->order_number, 'amount' => $order->amount]
        );

        return redirect()->route('user.orders.show', $order)
            ->with('success', 'Order created. Please complete payment to receive your access key.');
    }

    public function download(Order $order)
    {
        $this->authorize('view', $order);

        if (! $order->isToolOrder() || ! $order->isConfirmed()) {
            return back()->with('error', 'This download is only available once your payment is approved.');
        }

        $download = $order->tool->primaryDownload;

        if (! $download || ! Storage::disk(config('software.download_disk', 'private'))->exists($download->file_path)) {
            abort(404);
        }

        return Storage::disk(config('software.download_disk', 'private'))
            ->download($download->file_path, $download->file_filename);
    }

    protected function generateOrderNumber(): string
    {
        do {
            $number = 'MBT-' . strtoupper(Str::random(6));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}
