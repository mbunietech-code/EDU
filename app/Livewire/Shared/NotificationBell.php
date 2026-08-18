<?php

namespace App\Livewire\Shared;

use Livewire\Component;

class NotificationBell extends Component
{
    protected function getListeners(): array
    {
        return ['echo-notification' => '$refresh'];
    }

    public function render()
    {
        $user = auth()->user();

        $unreadCount = 0;
        $recentNotifications = collect();

        if ($user) {
            $unreadCount = $user->unreadNotifications()->count();
            $recentNotifications = $user->notifications()->latest()->take(8)->get();
        }

        return view('livewire.shared.notification-bell', [
            'unreadCount' => $unreadCount,
            'recentNotifications' => $recentNotifications,
        ]);
    }
}