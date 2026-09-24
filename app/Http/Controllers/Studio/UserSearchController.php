<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Member lookup for the user picker (?q=, max 20; e-mail only shown to admins).
 *
 * Used for room members, course enrolments and instructor grants. The route
 * is behind can:learning.studio. Only active accounts are returned. Admins
 * match on name or any part of the e-mail address and see e-mails; everyone
 * else matches on name or an exact e-mail address, so the endpoint cannot be
 * used to harvest addresses letter by letter.
 */
class UserSearchController extends Controller
{
    private const LIMIT = 20;

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $q = trim((string) ($data['q'] ?? ''));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $viewer = $request->user();
        $showEmail = (bool) $viewer->is_admin;
        $like = '%'.addcslashes($q, '\\%_').'%';

        $users = User::query()
            ->select(['id', 'name', 'email'])
            ->where('status', 'active')
            ->where(function (Builder $w) use ($like, $q, $showEmail) {
                $w->where('name', 'like', $like);

                if ($showEmail) {
                    $w->orWhere('email', 'like', $like);
                } else {
                    $w->orWhere('email', $q);
                }
            })
            ->orderBy('name')
            ->orderBy('id')
            ->limit(self::LIMIT)
            ->get();

        return response()->json($users->map(fn (User $u) => array_filter([
            'id' => $u->id,
            'name' => $u->name,
            'email' => $showEmail ? $u->email : null,
        ], fn ($value) => $value !== null))->values());
    }
}
