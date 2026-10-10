<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PracticeAttemptController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $input = $request->validate([
            'kind' => ['required', Rule::in(['reading', 'grammar'])],
            'contentId' => ['required', 'integer', 'min:1'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $query = DB::table('practice_attempts')->where('user_id', $request->user()->id)
            ->where('kind', $input['kind'])->where('content_id', $input['contentId']);
        $active = (clone $query)->where('status', 'in_progress')->orderByDesc('id')->first();
        $completed = (clone $query)->where('status', 'completed');
        $page = (int) ($input['page'] ?? 1);
        $history = (clone $completed)->orderByDesc('id')->forPage($page, 10)->get()
            ->map(fn (object $row): array => $this->summary($row));

        return response()->json([
            'active' => $active ? $this->attempt($active) : null,
            'latest' => ($latest = (clone $completed)->orderByDesc('id')->first()) ? $this->summary($latest) : null,
            'history' => $history,
            'page' => $page,
            'lastPage' => max(1, (int) ceil((clone $completed)->count() / 10)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $input = $request->validate([
            'kind' => ['required', Rule::in(['reading', 'grammar'])],
            'contentId' => ['required', 'integer', 'min:1'],
            'restart' => ['sometimes', 'boolean'],
        ]);
        $kind = $input['kind'];
        $contentId = (int) $input['contentId'];
        if (! $this->publishedContent($kind, $contentId)) {
            return $this->error('内容不存在或尚未发布', 404);
        }
        $userId = (int) $request->user()->id;
        $result = DB::transaction(function () use ($userId, $kind, $contentId, $input): ?object {
            DB::table('users')->where('id', $userId)->lockForUpdate()->first(['id']);
            $active = DB::table('practice_attempts')->where('user_id', $userId)
                ->where('kind', $kind)->where('content_id', $contentId)
                ->where('status', 'in_progress')->orderByDesc('id')->first();
            if ($active && empty($input['restart'])) {
                return $active;
            }
            $questions = DB::table($kind === 'reading' ? 'reading_questions' : 'grammar_questions')
                ->where($kind === 'reading' ? 'reading_item_id' : 'grammar_lesson_id', $contentId)
                ->orderBy('sort_order')->get()->map(fn (object $q): array => [
                    'id' => (int) $q->id,
                    'sortOrder' => (int) $q->sort_order,
                    'kind' => $q->kind,
                    'questionType' => $kind === 'reading' ? $q->question_type : null,
                    'prompt' => $q->prompt,
                    'promptCn' => $kind === 'reading' ? $q->prompt_cn : null,
                    'options' => $q->options_json ? json_decode($q->options_json, true) : null,
                    'answer' => $q->kind === 'choice' ? (int) $q->answer : $q->answer,
                    'explanation' => $q->explanation,
                ])->all();
            if (! count($questions)) {
                return null;
            }
            if ($active) {
                DB::table('practice_attempts')->where('id', $active->id)
                    ->update(['status' => 'abandoned', 'updated_at' => now()]);
            }
            $id = DB::table('practice_attempts')->insertGetId([
                'user_id' => $userId, 'kind' => $kind, 'content_id' => $contentId,
                'status' => 'in_progress',
                'questions_json' => json_encode($questions, JSON_UNESCAPED_UNICODE),
                'total_count' => count($questions), 'correct_count' => 0,
                'started_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);

            return DB::table('practice_attempts')->where('id', $id)->first();
        });
        if (! $result) {
            return $this->error('暂无练习题', 422);
        }

        return response()->json(['attempt' => $this->attempt($result)]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $row = DB::table('practice_attempts')->where('id', $id)
            ->where('user_id', $request->user()->id)->first();

        return $row ? response()->json(['attempt' => $this->attempt($row)]) : $this->error('练习记录不存在', 404);
    }

    public function answer(Request $request, int $id): JsonResponse
    {
        $input = $request->validate([
            'questionId' => ['required', 'integer', 'min:1'],
            'answer' => ['required'],
        ]);
        $userId = (int) $request->user()->id;
        $result = DB::transaction(function () use ($userId, $id, $input): array {
            $row = DB::table('practice_attempts')->where('id', $id)
                ->where('user_id', $userId)->lockForUpdate()->first();
            if (! $row) {
                return ['error' => '练习记录不存在', 'status' => 404];
            }
            $questions = json_decode($row->questions_json, true) ?: [];
            $answers = DB::table('practice_attempt_answers')->where('attempt_id', $id)
                ->orderBy('id')->get()->keyBy('question_id');
            $questionId = (int) $input['questionId'];
            $existing = $answers->get($questionId);
            if ($existing) {
                return ['attempt' => $this->attempt($row)];
            }
            if ($row->status !== 'in_progress') {
                return ['error' => '本轮练习已经结束', 'status' => 422];
            }
            $next = $questions[$answers->count()] ?? null;
            if (! $next || $next['id'] !== $questionId) {
                return ['error' => '请按顺序完成题目', 'status' => 422];
            }
            $answer = $input['answer'];
            if ($next['kind'] === 'choice') {
                if (! is_int($answer) || $answer < 0 || $answer >= count($next['options'] ?? [])) {
                    return ['error' => '请选择有效选项', 'status' => 422];
                }
                $correct = $answer === $next['answer'];
            } else {
                if (! is_string($answer) || trim($answer) === '' || mb_strlen($answer) > 1000) {
                    return ['error' => '请输入有效答案', 'status' => 422];
                }
                $answer = trim($answer);
                $normal = fn (string $text): string => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text)));
                $correct = $normal($answer) === $normal((string) $next['answer']);
            }
            DB::table('practice_attempt_answers')->insert([
                'attempt_id' => $id, 'question_id' => $questionId,
                'submitted_answer_json' => json_encode($answer, JSON_UNESCAPED_UNICODE),
                'is_correct' => $correct, 'answered_at' => now(),
            ]);
            $done = $answers->count() + 1 === count($questions);
            DB::table('practice_attempts')->where('id', $id)->update([
                'correct_count' => (int) $row->correct_count + ($correct ? 1 : 0),
                'status' => $done ? 'completed' : 'in_progress',
                'completed_at' => $done ? now() : null,
                'updated_at' => now(),
            ]);
            if ($done && $row->kind === 'grammar') {
                DB::table('recent_activity')->updateOrInsert(
                    ['user_id' => $userId, 'category' => 'grammar', 'target_id' => $row->content_id],
                    ['last_active_at' => now()]
                );
            }
            $updated = DB::table('practice_attempts')->where('id', $id)->first();

            return ['attempt' => $this->attempt($updated)];
        });

        return isset($result['error'])
            ? $this->error($result['error'], $result['status'])
            : response()->json($result);
    }

    private function publishedContent(string $kind, int $id): bool
    {
        if ($kind === 'grammar') {
            return DB::table('grammar_lessons')->where('id', $id)->where('status', 'published')->exists();
        }

        return DB::table('reading_items')->where('id', $id)->where('status', 'published')
            ->where(function ($query): void {
                $query->where('kind', '!=', 'chapter')->orWhereExists(function ($series): void {
                    $series->selectRaw('1')->from('series')->whereColumn('series.id', 'reading_items.series_id')
                        ->where('series.status', 'published');
                });
            })->exists();
    }

    private function summary(object $row): array
    {
        return [
            'id' => (int) $row->id, 'kind' => $row->kind, 'contentId' => (int) $row->content_id,
            'totalCount' => (int) $row->total_count, 'correctCount' => (int) $row->correct_count,
            'completedAt' => $row->completed_at,
        ];
    }

    private function attempt(object $row): array
    {
        $answers = DB::table('practice_attempt_answers')->where('attempt_id', $row->id)
            ->get()->keyBy('question_id');
        $questions = collect(json_decode($row->questions_json, true) ?: [])->map(function (array $question) use ($answers): array {
            $answer = $answers->get($question['id']);
            $public = [
                'id' => $question['id'], 'sortOrder' => $question['sortOrder'],
                'kind' => $question['kind'], 'questionType' => $question['questionType'],
                'prompt' => $question['prompt'], 'promptCn' => $question['promptCn'],
                'options' => $question['options'],
            ];
            if ($answer) {
                $public['submittedAnswer'] = json_decode($answer->submitted_answer_json, true);
                $public['correct'] = (bool) $answer->is_correct;
                $public['correctAnswer'] = $question['answer'];
                $public['explanation'] = $question['explanation'];
            }

            return $public;
        })->all();

        return $this->summary($row) + [
            'status' => $row->status, 'answeredCount' => $answers->count(),
            'startedAt' => $row->started_at, 'questions' => $questions,
        ];
    }

    private function error(string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $status === 404 ? 'NOT_FOUND' : 'INVALID_PRACTICE', 'message' => $message]], $status);
    }
}
