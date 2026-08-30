<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class TeamController extends Controller
{
    private function guard(Request $request): void
    {
        abort_unless(
            Schema::hasColumn('users', 'role'),
            409,
            'Apply the "roles and permissions" schema change first (on the website).',
        );
        abort_unless($request->user()->isSuperAdmin(), 403, 'Super admin only.');
    }

    public function index(Request $request): JsonResponse
    {
        $this->guard($request);

        $admins = User::whereIn('role', [Permissions::ROLE_ADMIN, Permissions::ROLE_SUPER_ADMIN])
            ->orderByDesc('role')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $admins->map(fn (User $u) => $this->row($u, $request->user()))->all(),
            'meta' => [
                'groups' => $this->groups(),
                'roles' => Permissions::assignableRoles(),
            ],
        ]);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $this->guard($request);

        return response()->json(['data' => $this->row($user, $request->user())]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->guard($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::min(8)],
            'role' => ['required', Rule::in(array_keys(Permissions::assignableRoles()))],
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ]);

        $user = new User();
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->password = Hash::make($data['password']);
        $user->status = 'active';
        $user->email_verified_at = now();
        $user->role = $data['role'];
        $user->permissions = $data['role'] === Permissions::ROLE_SUPER_ADMIN
            ? []
            : Permissions::sanitize($data['permissions'] ?? []);
        $user->save();

        ActivityLog::log('admin_created', 'User', $user->id, ['email' => $user->email, 'role' => $user->role]);

        return response()->json(['data' => $this->row($user, $request->user()), 'message' => 'Admin added.'], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->guard($request);
        $this->assertManageable($request, $user);

        $data = $request->validate([
            'role' => ['required', Rule::in(array_keys(Permissions::assignableRoles()))],
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ]);

        if ($user->role === Permissions::ROLE_SUPER_ADMIN
            && $data['role'] !== Permissions::ROLE_SUPER_ADMIN
            && $this->superAdminCount() <= 1) {
            return response()->json(['message' => 'There must be at least one super admin.'], 422);
        }

        $user->role = $data['role'];
        $user->permissions = $data['role'] === Permissions::ROLE_SUPER_ADMIN
            ? []
            : Permissions::sanitize($data['permissions'] ?? []);
        $user->save();

        ActivityLog::log('admin_updated', 'User', $user->id, ['email' => $user->email, 'role' => $user->role]);

        return response()->json(['data' => $this->row($user, $request->user()), 'message' => 'Permissions updated.']);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->guard($request);
        $this->assertManageable($request, $user);

        if ($user->role === Permissions::ROLE_SUPER_ADMIN && $this->superAdminCount() <= 1) {
            return response()->json(['message' => 'There must be at least one super admin.'], 422);
        }

        $user->role = Permissions::ROLE_USER;
        $user->permissions = [];
        $user->save();

        ActivityLog::log('admin_revoked', 'User', $user->id, ['email' => $user->email]);

        return response()->json(['message' => $user->name.' is no longer an admin.']);
    }

    private function row(User $u, User $viewer): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->role,
            'role_label' => str_replace('_', ' ', ucfirst($u->role)),
            'is_super_admin' => $u->role === Permissions::ROLE_SUPER_ADMIN,
            'permissions' => $u->permissions ?? [],
            'is_self' => $u->id === $viewer->id,
        ];
    }

    private function groups(): array
    {
        $out = [];
        foreach (Permissions::groups() as $group => $perms) {
            $out[] = [
                'group' => $group,
                'permissions' => collect($perms)->map(fn ($label, $key) => [
                    'key' => $key,
                    'label' => $label,
                ])->values(),
            ];
        }

        return $out;
    }

    private function assertManageable(Request $request, User $user): void
    {
        abort_if($user->id === $request->user()->id, 403, 'You cannot change your own role here.');
        abort_unless(
            in_array($user->role, [Permissions::ROLE_ADMIN, Permissions::ROLE_SUPER_ADMIN], true),
            404,
        );
    }

    private function superAdminCount(): int
    {
        return User::where('role', Permissions::ROLE_SUPER_ADMIN)->count();
    }
}
