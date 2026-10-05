<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancePosition extends Model
{
    /**
     * Seniority levels: lower number = more senior. Steps of 10 leave room
     * to slot custom levels in between. Order matters: first match wins.
     * Mirrored in database/alters/0027_2026_10_05_finance_position_seniority.sql.
     */
    public const SENIORITY_RULES = [
        [100, ['assistant', 'intern', 'trainee', 'attendant']],
        [40, ['deputy']],
        [10, ['chair', 'chairman', 'chairperson', 'board']],
        [20, ['chief executive', 'ceo', 'founder', 'president']],
        [30, ['managing director', 'md']],
        [40, ['chief', 'cfo', 'coo', 'cto', 'cmo', 'cio']],
        [50, ['director', 'general manager']],
        [60, ['head']],
        [70, ['manager']],
        [80, ['supervisor', 'lead', 'coordinator']],
    ];

    public const DEFAULT_SENIORITY = 90;

    protected $fillable = ['finance_department_id', 'name', 'code', 'salary_grade', 'seniority', 'description', 'status'];

    protected $casts = [
        'seniority' => 'integer',
    ];

    public static function guessSeniority(string $name): int
    {
        $words = ' '.trim(preg_replace('/[^a-z]+/', ' ', strtolower($name))).' ';

        foreach (self::SENIORITY_RULES as [$level, $keywords]) {
            foreach ($keywords as $keyword) {
                if (str_contains($words, ' '.$keyword.' ')) {
                    return $level;
                }
            }
        }

        return self::DEFAULT_SENIORITY;
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(FinanceDepartment::class, 'finance_department_id');
    }

    public function staff(): HasMany
    {
        return $this->hasMany(FinanceStaff::class);
    }
}
