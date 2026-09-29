<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Private one-to-one Team Chat. A direct chat is an admin group with exactly
 * two members, identified by direct_key "<lower user id>:<higher user id>" —
 * only those two can read it (super admins included).
 *
 * The old shared threads (admin_conversations, visible to every super admin)
 * are copied into direct chats between the admin and the super admin who
 * answered them most; with no answer, the first super admin. The old tables
 * stay untouched apart from peer_id / migrated_at. Mirrors
 * database/alters/0016_2026_09_29_private_team_chat.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_groups', function (Blueprint $table) {
            $table->string('direct_key', 41)->nullable()->after('name');
            $table->unique('direct_key');
        });

        Schema::table('admin_conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('peer_id')->nullable();
            $table->timestamp('migrated_at')->nullable();
        });

        $this->moveLegacyThreads();
    }

    public function down(): void
    {
        Schema::table('admin_conversations', function (Blueprint $table) {
            $table->dropColumn(['peer_id', 'migrated_at']);
        });

        DB::table('admin_groups')->whereNotNull('direct_key')->delete();

        Schema::table('admin_groups', function (Blueprint $table) {
            $table->dropUnique(['direct_key']);
            $table->dropColumn('direct_key');
        });
    }

    private function moveLegacyThreads(): void
    {
        $firstSuperAdmin = fn (int $not) => DB::table('users')
            ->where('is_admin', true)
            ->where(fn ($q) => $q->where('role', 'super_admin')->orWhereNull('role')->orWhere('role', ''))
            ->where('id', '!=', $not)
            ->orderBy('id')
            ->value('id');

        foreach (DB::table('admin_conversations')->whereNull('migrated_at')->orderBy('id')->get() as $conversation) {
            $messages = DB::table('admin_messages')->where('admin_conversation_id', $conversation->id)->orderBy('id')->get();

            if ($messages->isEmpty()) {
                continue;
            }

            $peer = $messages->where('sender_id', '!=', $conversation->admin_id)
                ->countBy('sender_id')
                ->sortDesc()
                ->keys()
                ->first() ?? $firstSuperAdmin($conversation->admin_id);

            if (! $peer) {
                continue;
            }

            $pair = [min($conversation->admin_id, $peer), max($conversation->admin_id, $peer)];
            $key = implode(':', $pair);

            $groupId = DB::table('admin_groups')->where('direct_key', $key)->value('id')
                ?? DB::table('admin_groups')->insertGetId([
                    'name' => 'Private chat',
                    'direct_key' => $key,
                    'created_by' => $conversation->admin_id,
                    'created_at' => $conversation->created_at,
                    'updated_at' => $conversation->updated_at,
                ]);

            foreach ($messages as $m) {
                DB::table('admin_group_messages')->insert([
                    'admin_group_id' => $groupId,
                    'sender_id' => $m->sender_id,
                    'type' => $m->type,
                    'body' => $m->body,
                    'file_path' => $m->file_path,
                    'file_name' => $m->file_name,
                    'edited_at' => $m->edited_at,
                    'is_deleted' => $m->is_deleted,
                    'created_at' => $m->created_at,
                    'updated_at' => $m->updated_at,
                ]);
            }

            $lastRead = DB::table('admin_group_messages')->where('admin_group_id', $groupId)->max('id');

            foreach ($pair as $userId) {
                DB::table('admin_group_members')->updateOrInsert(
                    ['admin_group_id' => $groupId, 'user_id' => $userId],
                    ['last_read_message_id' => $lastRead, 'created_at' => now(), 'updated_at' => now()],
                );
            }

            DB::table('admin_conversations')->where('id', $conversation->id)
                ->update(['peer_id' => $peer, 'migrated_at' => now()]);
        }
    }
};
