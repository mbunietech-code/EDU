<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fresh-install / test counterpart of database/alters/0001_2026_08_30_roles_and_permissions.sql
 * (the live database applies the .sql version from the admin Database page).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role', 20)->default('user')->after('is_admin');
            }
            if (! Schema::hasColumn('users', 'permissions')) {
                $table->json('permissions')->nullable()->after('role');
            }
        });

        \App\Models\User::query()->where('is_admin', true)->where('role', 'user')
            ->update(['role' => 'admin']);

        $firstAdminId = \App\Models\User::query()->where('is_admin', true)->min('id');
        if ($firstAdminId) {
            \App\Models\User::query()->whereKey($firstAdminId)->update(['role' => 'super_admin']);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'permissions')) {
                $table->dropColumn('permissions');
            }
            if (Schema::hasColumn('users', 'role')) {
                $table->dropColumn('role');
            }
        });
    }
};
