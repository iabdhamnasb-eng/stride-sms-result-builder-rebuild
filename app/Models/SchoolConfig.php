<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Minimal SchoolConfig used by ResultCardService.
 *
 * NOTE: If STRIDE already has its own SchoolConfig model/helper, keep that
 * one and delete this fallback file. The service only relies on the static
 * getValue() signature below.
 */
class SchoolConfig extends Model
{
    protected $table = 'school_configs';

    protected $fillable = [
        'school_id',
        'section',
        'key',
        'value',
    ];

    public static function getValue(?int $schoolId, string $section, string $key, mixed $default = null): mixed
    {
        if ($schoolId === null) {
            return $default;
        }

        return Cache::remember(
            "school_config.{$schoolId}.{$section}.{$key}",
            now()->addMinutes(30),
            function () use ($schoolId, $section, $key, $default) {
                $row = static::where('school_id', $schoolId)
                    ->where('section', $section)
                    ->where('key', $key)
                    ->first();

                return $row->value ?? $default;
            }
        );
    }

    public static function setValue(?int $schoolId, string $section, string $key, mixed $value): void
    {
        if ($schoolId === null) {
            return;
        }

        static::updateOrCreate(
            ['school_id' => $schoolId, 'section' => $section, 'key' => $key],
            ['value' => $value]
        );

        Cache::forget("school_config.{$schoolId}.{$section}.{$key}");
    }
}
