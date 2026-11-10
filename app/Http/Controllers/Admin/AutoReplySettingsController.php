<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Services\AutoReplyService;
use Illuminate\Http\Request;

class AutoReplySettingsController extends Controller
{
    public function __construct(private AutoReplyService $autoReply)
    {
    }

    public function edit()
    {
        return view('admin.chat.auto-reply', [
            'enabled' => $this->autoReply->enabled(),
            'minutes' => $this->autoReply->minutes(),
            'message' => $this->autoReply->messageTemplate(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate(AutoReplyService::RULES);

        $this->autoReply->save($request->boolean('enabled'), (int) $data['minutes'], $data['message']);

        return back()->with('success', 'Auto-reply settings saved.');
    }
}
