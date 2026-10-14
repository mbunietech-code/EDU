<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two admin tools:
 *  - company_assets: everything Mbunie pays for (domains, hosting, servers,
 *    software, licences, devices…) with when it was bought, when it ends,
 *    where it is bought and who looks after it;
 *  - admin_tasks (+ comments): work an admin gives to a team member, who is
 *    picked by name and job title (users.job_title: CEO, MD, Developer…).
 *
 * Live database: database/alters/0032_2026_10_14_company_assets_and_admin_tasks.sql
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('job_title', 80)->nullable();
        });

        Schema::create('company_assets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('category', 30)->default('other');
            $table->string('vendor', 150)->nullable();          // where it is bought (Hostinger, Namecheap…)
            $table->string('purchase_url', 500)->nullable();    // where to buy / renew it
            $table->string('account', 150)->nullable();         // which login / email it is registered under (never a password)
            $table->date('purchased_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('billing_cycle', 20)->default('yearly');
            $table->decimal('cost', 12, 2)->nullable();
            $table->string('currency', 3)->default('TZS');
            $table->boolean('auto_renew')->default(false);
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('reminded_days')->nullable(); // last "N days left" reminder sent
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('expires_at');
        });

        Schema::create('admin_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('priority', 10)->default('normal');  // low | normal | high | urgent
            $table->string('status', 15)->default('todo');      // todo | in_progress | done | cancelled
            $table->date('due_at')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->date('reminded_on')->nullable();             // last due-date reminder
            $table->timestamps();

            $table->index(['assigned_to', 'status']);
        });

        Schema::create('admin_task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_task_id')->constrained('admin_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_task_comments');
        Schema::dropIfExists('admin_tasks');
        Schema::dropIfExists('company_assets');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('job_title'));
    }
};
