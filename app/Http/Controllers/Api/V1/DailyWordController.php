<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DailyWordController extends Controller
{
    public function today(): JsonResponse
    {
        $date = now('Asia/Shanghai')->toDateString();
        $plan = DB::table('daily_word_plans')->where('plan_date', $date)->first();
        if (! $plan) {
            $this->createAutomaticPlan($date);
            $plan = DB::table('daily_word_plans')->where('plan_date', $date)->first();
        }
        if (! $plan || $plan->status !== 'published') {
            return response()->json(['date' => $date, 'planId' => null, 'items' => []]);
        }
        $items = DB::table('daily_word_plan_items as i')
            ->join('words as w', 'w.id', '=', 'i.word_id')
            ->where('i.plan_id', $plan->id)->where('w.status', 'published')
            ->orderBy('i.sort_order')->get()
            ->map(fn ($row) => [
                'id' => (int) $row->word_id, 'headword' => $row->headword,
                'ipa' => $row->ipa, 'partOfSpeech' => $row->part_of_speech,
                'meaning' => $row->meaning, 'frequencyLabel' => $row->frequency_label,
            ]);

        return response()->json(['date' => $date, 'planId' => $plan->id, 'items' => $items]);
    }

    private function createAutomaticPlan(string $date): void
    {
        $wordIds = DB::table('words')->where('status', 'published')->orderBy('id')->pluck('id')->all();
        if ($wordIds === []) {
            return;
        }
        DB::transaction(function () use ($date, $wordIds): void {
            DB::table('daily_word_plans')->insertOrIgnore([
                'plan_date' => $date, 'status' => 'published', 'published_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $plan = DB::table('daily_word_plans')->where('plan_date', $date)->first();
            if ($plan->status !== 'published' || DB::table('daily_word_plan_items')->where('plan_id', $plan->id)->exists()) {
                return;
            }
            $group = ((int) floor(strtotime($date.' UTC') / 86400)) % (int) ceil(count($wordIds) / 4);
            $start = $group * 4;
            for ($position = 0; $position < min(4, count($wordIds)); $position++) {
                DB::table('daily_word_plan_items')->insertOrIgnore([
                    'plan_id' => $plan->id,
                    'word_id' => $wordIds[($start + $position) % count($wordIds)],
                    'sort_order' => $position + 1,
                ]);
            }
        });
    }
}
