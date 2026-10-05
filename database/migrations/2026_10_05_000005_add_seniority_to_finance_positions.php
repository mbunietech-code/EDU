<?php

use App\Models\FinancePosition;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('finance_positions') || Schema::hasColumn('finance_positions', 'seniority')) {
            return;
        }

        Schema::table('finance_positions', function (Blueprint $table) {
            $table->unsignedSmallInteger('seniority')->default(FinancePosition::DEFAULT_SENIORITY)->after('salary_grade');
        });

        foreach (DB::table('finance_positions')->get(['id', 'name']) as $position) {
            DB::table('finance_positions')->where('id', $position->id)->update([
                'seniority' => FinancePosition::guessSeniority($position->name),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('finance_positions', 'seniority')) {
            Schema::table('finance_positions', function (Blueprint $table) {
                $table->dropColumn('seniority');
            });
        }
    }
};
