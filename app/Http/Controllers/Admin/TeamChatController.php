<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminGroup;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Internal messaging between the admin team — separate from the customer
 * support chat (AdminChatController). Every chat is an AdminGroup: private
 * one-to-one chats (exactly two members) and multi-admin groups. Only the
 * members of a chat can read it; super admins get no special access.
 * Messages themselves are handled by AdminGroupChatController.
 */
class TeamChatController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $chats = $this->chatsFor($user);

        $ready = $this->privateChatsReady();

        // Admins this user has no private chat with yet.
        $talkingTo = $chats->filter->isDirect()->flatMap->directUserIds()->all();
        $others = $ready
            ? User::where('is_admin', true)->where('id', '!=', $user->id)->whereNotIn('id', $talkingTo)->orderBy('name')->get()
            : collect();

        return view('admin.team-chat.index', compact('chats', 'others', 'ready'));
    }

    public function start(Request $request, User $admin)
    {
        $user = $request->user();

        abort_unless($admin->is_admin && $admin->id !== $user->id, 404);
        abort_unless($this->privateChatsReady(), 503, 'Apply database alter 0016 (Admin → Database) to enable private chats.');

        return redirect()->route('admin.team-chat.groups.show', AdminGroup::directBetween($user, $admin));
    }

    /** Old /admin/team-chat/{id} links from the shared-thread days. */
    public function legacy()
    {
        return redirect()->route('admin.team-chat.index');
    }

    /**
     * Chats this user belongs to, newest activity first, each with its
     * unread count attached. Tolerates the group tables not existing yet
     * (code deployed before the alter was applied).
     *
     * @return \Illuminate\Support\Collection<int,AdminGroup>
     */
    protected function chatsFor(User $user)
    {
        try {
            return AdminGroup::with(['members', 'latestMessage.sender'])
                ->whereHas('members', fn ($q) => $q->whereKey($user->id))
                ->get()
                ->each(fn (AdminGroup $g) => $g->unread = $g->unreadFor($user))
                ->sortByDesc(fn ($g) => $g->latestMessage?->created_at ?? $g->created_at)
                ->values();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    protected function privateChatsReady(): bool
    {
        try {
            return Schema::hasColumn('admin_groups', 'direct_key');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
