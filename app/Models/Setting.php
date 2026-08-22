<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = self::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    public static function set(string $key, mixed $value, string $type = 'string', ?string $group = null): void
    {
        $attributes = ['value' => $value, 'type' => $type];

        if ($group !== null) {
            $attributes['group'] = $group;
        }

        self::updateOrCreate(['key' => $key], $attributes);
    }
}
