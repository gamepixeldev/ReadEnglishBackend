<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class RewardedHintSettings
{
    public static function all(): array
    {
        $settings = DB::table('app_settings')->whereIn('key', [
            'rewarded_ads_enabled', 'rewarded_ad_unit_id', 'hint_answer_seconds',
        ])->pluck('value', 'key');

        return [
            'enabled' => ($settings['rewarded_ads_enabled'] ?? '0') === '1',
            'adUnitId' => trim((string) ($settings['rewarded_ad_unit_id'] ?? '')),
            'answerSeconds' => max(1, min(30, (int) ($settings['hint_answer_seconds'] ?? 3))),
        ];
    }

    public static function forUser(?User $user): array
    {
        $settings = self::all();
        $mode = $user && ! $user->is_admin ? $user->rewarded_ads_mode : null;
        $settings['enabled'] = match ($mode) {
            'enabled' => true,
            'disabled' => false,
            default => $settings['enabled'],
        };

        return $settings;
    }

    public static function save(array $values): void
    {
        foreach ([
            'rewarded_ads_enabled' => $values['enabled'] ? '1' : '0',
            'rewarded_ad_unit_id' => trim($values['adUnitId']),
            'hint_answer_seconds' => (string) $values['answerSeconds'],
        ] as $key => $value) {
            DB::table('app_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now(), 'created_at' => now()],
            );
        }
    }
}
