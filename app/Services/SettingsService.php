<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Validation\Rule;

/**
 * The office-editable settings, with their defaults. A setting that has no row yet simply has its default,
 * so a fresh install and an upgraded one behave identically until somebody changes something.
 * Everything defaults to OFF / zero: nothing is charged or sent that the office has not switched on.
 */
class SettingsService
{
    /** key => [default, validation rules, type] */
    public const DEFINITIONS = [
        'bill_due_day' => ['10', ['integer', 'between:1,28'], 'int'],
        'late_fee_mode' => ['NONE', [], 'enum'],
        'late_fee_value' => ['0', ['numeric', 'min:0'], 'number'],
        'late_fee_grace_days' => ['0', ['integer', 'between:0,60'], 'int'],
        'auto_generate_bills' => ['0', ['boolean'], 'bool'],
        'sms_enabled' => ['0', ['boolean'], 'bool'],
        'reminders_enabled' => ['0', ['boolean'], 'bool'],
        'reminder_days_before_due' => ['3', ['integer', 'between:0,20'], 'int'],
        'reminder_repeat_days' => ['7', ['integer', 'between:1,60'], 'int'],
    ];

    public function get(string $key): string
    {
        $row = AppSetting::where('key', $key)->first();

        return $row?->value ?? self::DEFINITIONS[$key][0];
    }

    public function bool(string $key): bool
    {
        return $this->get($key) === '1';
    }

    public function int(string $key): int
    {
        return (int) $this->get($key);
    }

    /** @return array<string, string> */
    public function all(): array
    {
        $rows = AppSetting::pluck('value', 'key');
        $out = [];
        foreach (self::DEFINITIONS as $key => [$default]) {
            $out[$key] = (string) ($rows[$key] ?? $default);
        }

        return $out;
    }

    /** @return array<string, array> validation rules for a settings update (every key optional) */
    public static function rules(): array
    {
        $rules = [];
        foreach (self::DEFINITIONS as $key => [, $r]) {
            $rules[$key] = array_merge(['sometimes'], $key === 'late_fee_mode' ? [Rule::in(['NONE', 'PERCENT', 'FIXED'])] : $r);
        }

        return $rules;
    }

    /** @param  array<string, mixed>  $values */
    public function update(array $values): array
    {
        foreach ($values as $key => $value) {
            if (! isset(self::DEFINITIONS[$key])) {
                continue;
            }
            $stored = self::DEFINITIONS[$key][2] === 'bool' ? ($value ? '1' : '0') : (string) $value;
            AppSetting::updateOrCreate(['key' => $key], ['value' => $stored]);
        }

        return $this->all();
    }
}
