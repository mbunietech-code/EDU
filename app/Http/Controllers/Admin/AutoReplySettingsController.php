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
        $data = $request->validate([
            'minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'message' => ['required', 'string', 'max:1000'],
        ]);

        Setting::set('autoreply_enabled', $request->boolean('enabled') ? '1' : '0', 'string', 'autoreply');
        Setting::set('autoreply_minutes', (string) $data['minutes'], 'integer', 'autoreply');
        Setting::set('autoreply_message', $data['message'], 'string', 'autoreply');

        ActivityLog::log('chat_auto_reply_updated', 'Setting', null, [
            'enabled' => $request->boolean('enabled'),
            'minutes' => $data['minutes'],
        ]);

        return back()->with('success', 'Auto-reply settings saved.');
    }
}
