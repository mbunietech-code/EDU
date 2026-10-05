<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['finance_staff', 'finance_payroll_items'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)
                ->where('staff_number', 'like', 'STF-%')
                ->get(['id', 'staff_number'])
                ->each(function ($row) use ($table) {
                    $digits = substr($row->staff_number, 4);
                    if (! ctype_digit($digits)) {
                        return;
                    }

                    DB::table($table)->where('id', $row->id)->update([
                        'staff_number' => 'Mhub-'.str_pad((string) (int) $digits, 3, '0', STR_PAD_LEFT),
                    ]);
                });
        }
    }

    public function down(): void
    {
        //
    }
};
