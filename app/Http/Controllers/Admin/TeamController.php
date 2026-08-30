<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class TeamController extends Controller
{
    private function ensureInstalled(): void
    {
        abort_unless(
            Schema::hasColumn('users', 'role'),
            409,
            'Apply the "roles and permissions" schema change first (Database page).',
        );
    }

    public function index()
    {
        $this->ensureInstalled();

        $admins = User::query()
            ->whereIn('role', [Permissions::ROLE_ADMIN, Permissions::ROLE_SUPER_ADMIN])
            ->orderByDesc('role')
            ->orderBy('name')
            ->get();

        return view('admin.team.index', [
            'admins' => $admins,
            'groups' => Permissions::groups(),
        ]);
    }

    public function create()
    {
        $this->ensureInstalled();

        return view('admin.team.create', [
            'roles' => Permissions::assignableRoles(),
            'groups' => Permissions::groups(),
        ]);
    }

    public function store()
    {
        $this->ensureInstalled();

        $data = request()->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::min(8)],
            'role' => ['required', Rule::in(array_keys(Permissions::assignableRoles()))],
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ]);

        $role = $this->cappedRole($data['role']);

        $user = new User();
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->password = Hash::make($data['password']);
        $user->status = 'active';
        $user->email_verified_at = now();
        $user->role = $role;
        $user->permissions = $role === Permissions::ROLE_SUPER_ADMIN
            ? []
            : Permissions::sanitize($data['permissions'] ?? []);
        $user->save();

        ActivityLog::log('admin_created', 'User', $user->id, [
            'email' => $user->email,
            'role' => $role,
        ]);

        return redirect()->route('admin.team.index')
            ->with('success', "{$user->name} added as ".str_replace('_', ' ', $role).'.');
    }

    public function edit(User $user)
    {
        $this->ensureInstalled();
        $this->assertManageable($user);

        return view('admin.team.edit', [
            'member' => $user,
            'roles' => Permissions::assignableRoles(),
            'groups' => Permissions::groups(),
        ]);
    }

    public function update(User $user)
    {
        $this->ensureInstalled();
        $this->assertManageable($user);

        $data = request()->validate([
            'role' => ['required', Rule::in(array_keys(Permissions::assignableRoles()))],
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ]);

        $role = $this->cappedRole($data['role']);

        // Never remove the last super admin.
        if ($user->role === Permissions::ROLE_SUPER_ADMIN && $role !== Permissions::ROLE_SUPER_ADMIN
            && $this->superAdminCount() <= 1) {
            return back()->with('error', 'There must be at least one super admin.');
        }

        $user->role = $role;
        $user->permissions = $role === Permissions::ROLE_SUPER_ADMIN
            ? []
            : Permissions::sanitize($data['permissions'] ?? []);
        $user->save();

        ActivityLog::log('admin_updated', 'User', $user->id, [
            'email' => $user->email,
            'role' => $role,
        ]);

        return redirect()->route('admin.team.index')->with('success', 'Permissions updated.');
    }

    public function destroy(User $user)
    {
        $this->ensureInstalled();
        $this->assertManageable($user);

        if ($user->role === Permissions::ROLE_SUPER_ADMIN && $this->superAdminCount() <= 1) {
            return back()->with('error', 'There must be at least one super admin.');
        }

        $user->role = Permissions::ROLE_USER;
        $user->permissions = [];
        $user->save();

        ActivityLog::log('admin_revoked', 'User', $user->id, ['email' => $user->email]);

        return redirect()->route('admin.team.index')
            ->with('success', "{$user->name} is no longer an admin.");
    }

    /**
     * Only a super admin may grant the super admin role.
     */
    private function cappedRole(string $requested): string
    {
        if ($requested === Permissions::ROLE_SUPER_ADMIN && ! auth()->user()->isSuperAdmin()) {
            return Permissions::ROLE_ADMIN;
        }

        return $requested;
    }

    private function assertManageable(User $user): void
    {
        abort_if($user->id === auth()->id(), 403, 'You cannot change your own role here.');
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
