<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StudyProgressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $readingIds = DB::table('reading_completions')->where('user_id', $userId)->pluck('reading_item_id');
        $wordIds = DB::table('word_learning_events')->where('user_id', $userId)->distinct()->pluck('word_id');
        $favoriteIds = DB::table('word_favorites')->where('user_id', $userId)->pluck('word_id');
        $allWordIds = $wordIds->concat($favoriteIds)->unique()->values();
        $wordSummaries = DB::table('words')->whereIn('id', $allWordIds)
            ->select('id', 'headword', 'part_of_speech', 'meaning')->get()
            ->mapWithKeys(fn ($word) => [$word->id => [
                'id' => (int) $word->id,
                'headword' => $word->headword,
                'partOfSpeech' => $word->part_of_speech,
                'meaning' => $word->meaning,
            ]]);
        $puzzles = DB::table('puzzle_progress')->where('user_id', $userId)->get()
            ->mapWithKeys(fn ($row) => [$row->puzzle_set_id => [
                'cursor' => (int) $row->cursor_next,
                'completed' => $row->completed_at !== null,
                'updatedAt' => $row->updated_at,
            ]]);
        $daily = DB::table('daily_word_completions as c')
            ->join('daily_word_plans as p', 'p.id', '=', 'c.plan_id')
            ->where('c.user_id', $userId)
            ->select('p.plan_date', 'c.word_id')->get()
            ->groupBy('plan_date')->map(fn ($rows) => $rows->pluck('word_id')->all());
        $recent = DB::table('recent_activity as a')
            ->leftJoin('reading_items as r', function ($join): void {
                $join->on('r.id', '=', 'a.target_id')->where('a.category', '=', 'reading');
            })
            ->leftJoin('series as s', 's.id', '=', 'r.series_id')
            ->leftJoin('words as w', function ($join): void {
                $join->on('w.id', '=', 'a.target_id')->where('a.category', '=', 'word');
            })
            ->leftJoin('grammar_lessons as g', function ($join): void {
                $join->on('g.id', '=', 'a.target_id')->where('a.category', '=', 'grammar');
            })
            ->leftJoin('puzzle_sets as p', function ($join): void {
                $join->on('p.id', '=', 'a.target_id')->where('a.category', '=', 'puzzle');
            })
            ->where('a.user_id', $userId)->orderByDesc('a.last_active_at')
            ->select('a.category', 'a.target_id', 'a.last_active_at', 'r.kind as reading_kind',
                'r.title as reading_title', 'r.translation as reading_translation', 'r.series_id',
                'r.chapter_no', 's.translation as series_translation', 'w.headword',
                'w.meaning as word_meaning', 'w.part_of_speech', 'g.title as grammar_title',
                'g.level as grammar_level', 'p.title as puzzle_title', 'p.subtitle as puzzle_subtitle')
            ->get()->map(fn ($row) => [
                'category' => $row->category,
                'targetId' => (int) $row->target_id,
                'lastActiveAt' => $row->last_active_at,
                'title' => $row->reading_title ?: ($row->headword ?: ($row->grammar_title ?: $row->puzzle_title)),
                'subtitle' => $row->category === 'reading'
                    ? ($row->reading_kind === 'chapter' ? ($row->series_translation ?: '外刊阅读') : ($row->reading_translation ?: '精读文章'))
                    : ($row->category === 'word' ? trim(($row->part_of_speech ?: '').' '.($row->word_meaning ?: ''))
                        : ($row->category === 'grammar' ? ($row->grammar_level ?: '语法专题') : $row->puzzle_subtitle)),
                'readingKind' => $row->reading_kind,
                'seriesId' => $row->series_id,
                'chapterNo' => $row->chapter_no,
                'puzzleTotal' => $row->category === 'puzzle'
                    ? DB::table('puzzle_sentences')->where('puzzle_set_id', $row->target_id)->count()
                    : null,
            ]);

        return response()->json([
            'readingIds' => $readingIds,
            'wordIds' => $wordIds,
            'favoriteWordIds' => $favoriteIds,
            'wordSummariesById' => $wordSummaries,
            'puzzles' => $puzzles,
            'dailyWordIdsByDate' => $daily,
            'recent' => $recent,
        ]);
    }

    public function clearRecent(Request $request): JsonResponse
    {
        DB::table('recent_activity')->where('user_id', $request->user()->id)->delete();

        return response()->json(['cleared' => true]);
    }

    public function completeReading(Request $request): JsonResponse
    {
        $input = $request->validate(['readingItemId' => ['required', 'integer', 'min:1']]);
        $id = (int) $input['readingItemId'];
        if (! DB::table('reading_items')->where('id', $id)->where('status', 'published')->exists()) {
            return $this->notFound();
        }
        $userId = $request->user()->id;
        DB::transaction(function () use ($userId, $id): void {
            DB::table('reading_completions')->insertOrIgnore([
                'user_id' => $userId, 'reading_item_id' => $id, 'completed_at' => now(),
            ]);
            $this->touchRecent($userId, 'reading', $id);
        });

        return response()->json(['readingItemId' => $id, 'completed' => true]);
    }

    public function completeWord(Request $request): JsonResponse
    {
        $input = $request->validate([
            'wordId' => ['required', 'integer', 'min:1'],
            'sourceKind' => ['required', Rule::in(['daily', 'reading', 'independent'])],
            'readingItemId' => ['nullable', 'integer', 'min:1'],
            'planDate' => ['nullable', 'date_format:Y-m-d'],
            'requestId' => ['required', 'string', 'max:64'],
        ]);
        $wordId = (int) $input['wordId'];
        $source = $input['sourceKind'];
        if (! DB::table('words')->where('id', $wordId)->where('status', 'published')->exists()) {
            return $this->notFound();
        }
        $readingId = null;
        $plan = null;
        if ($source === 'reading') {
            $readingId = (int) ($input['readingItemId'] ?? 0);
            if (! DB::table('reading_words')->join('reading_items', 'reading_items.id', '=', 'reading_words.reading_item_id')
                ->where('reading_words.reading_item_id', $readingId)->where('reading_words.word_id', $wordId)
                ->where('reading_items.status', 'published')->exists()) {
                return $this->notFound();
            }
        } elseif ($source === 'daily') {
            $date = $input['planDate'] ?? '';
            $plan = DB::table('daily_word_plans as p')
                ->join('daily_word_plan_items as i', 'i.plan_id', '=', 'p.id')
                ->where('p.plan_date', $date)->where('p.status', 'published')
                ->where('i.word_id', $wordId)->select('p.id')->first();
            if (! $plan) {
                return $this->notFound();
            }
        }
        $userId = $request->user()->id;
        DB::transaction(function () use ($input, $userId, $wordId, $source, $readingId, $plan): void {
            $inserted = DB::table('word_learning_events')->insertOrIgnore([
                'user_id' => $userId,
                'word_id' => $wordId,
                'source_kind' => $source,
                'reading_item_id' => $readingId,
                'plan_date' => $source === 'daily' ? $input['planDate'] : null,
                'completed_at' => now(),
                'request_id' => $input['requestId'],
            ]);
            if (! $inserted) {
                return;
            }
            if ($plan) {
                DB::table('daily_word_completions')->insertOrIgnore([
                    'user_id' => $userId, 'plan_id' => $plan->id,
                    'word_id' => $wordId, 'completed_at' => now(),
                ]);
            }
            $this->touchRecent($userId, 'word', $wordId);
        });

        return response()->json(['wordId' => $wordId, 'completed' => true]);
    }

    public function favoriteWord(Request $request, int $id): JsonResponse
    {
        if (! DB::table('words')->where('id', $id)->where('status', 'published')->exists()) {
            return $this->notFound();
        }
        DB::table('word_favorites')->insertOrIgnore([
            'user_id' => $request->user()->id, 'word_id' => $id, 'created_at' => now(),
        ]);

        return response()->json(['wordId' => $id, 'favorite' => true]);
    }

    public function unfavoriteWord(Request $request, int $id): JsonResponse
    {
        DB::table('word_favorites')->where('user_id', $request->user()->id)->where('word_id', $id)->delete();

        return response()->json(['wordId' => $id, 'favorite' => false]);
    }

    public function savePuzzle(Request $request, int $id): JsonResponse
    {
        $input = $request->validate([
            'cursorNext' => ['required', 'integer', 'min:0'],
            'reset' => ['sometimes', 'boolean'],
        ]);
        if (! DB::table('puzzle_sets')->where('id', $id)->where('status', 'published')->exists()) {
            return $this->notFound();
        }
        $total = DB::table('puzzle_sentences')->where('puzzle_set_id', $id)->count();
        $cursor = (int) $input['cursorNext'];
        if ($total < 1 || $cursor > $total || (! empty($input['reset']) && $cursor !== 0)) {
            return response()->json(['error' => ['code' => 'INVALID_CURSOR', 'message' => '拼句进度无效']], 422);
        }
        $userId = $request->user()->id;
        $saved = DB::transaction(function () use ($userId, $id, $cursor, $total, $input): int {
            $previous = DB::table('puzzle_progress')->where('user_id', $userId)
                ->where('puzzle_set_id', $id)->lockForUpdate()->first();
            $next = ! empty($input['reset']) ? 0 : max($cursor, (int) ($previous->cursor_next ?? 0));
            DB::table('puzzle_progress')->updateOrInsert(
                ['user_id' => $userId, 'puzzle_set_id' => $id],
                ['cursor_next' => $next, 'completed_at' => $next === $total ? now() : null, 'updated_at' => now()]
            );
            $this->touchRecent($userId, 'puzzle', $id);

            return $next;
        });

        return response()->json(['puzzleSetId' => $id, 'cursorNext' => $saved, 'completed' => $saved === $total]);
    }

    public function openGrammar(Request $request, int $id): JsonResponse
    {
        if (! DB::table('grammar_lessons')->where('id', $id)->where('status', 'published')->exists()) {
            return $this->notFound();
        }
        $this->touchRecent($request->user()->id, 'grammar', $id);

        return response()->json(['grammarLessonId' => $id]);
    }

    private function touchRecent(int $userId, string $category, int $targetId): void
    {
        DB::table('recent_activity')->updateOrInsert(
            ['user_id' => $userId, 'category' => $category, 'target_id' => $targetId],
            ['last_active_at' => now()]
        );
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => '内容不存在或尚未发布']], 404);
    }
}
