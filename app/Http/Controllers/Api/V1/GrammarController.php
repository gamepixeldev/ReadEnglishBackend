<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\HtmlContentSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GrammarController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'level' => 'sometimes|string|max:32',
            'limit' => 'sometimes|integer|min:1|max:50',
            'cursor' => 'sometimes|string',
        ]);

        $query = DB::table('grammar_lessons')->where('status', 'published');
        if (isset($filters['level'])) {
            $query->where('level', $filters['level']);
        }
        $page = $query->orderBy('sort_order')->orderBy('id')->cursorPaginate($filters['limit'] ?? 20);

        return response()->json([
            'items' => $page->getCollection()->map(fn ($lesson) => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'summary' => $lesson->summary,
                'level' => $lesson->level,
            ])->all(),
            'nextCursor' => $page->nextCursor()?->encode(),
        ]);
    }

    public function show(int $id, HtmlContentSanitizer $sanitizer): JsonResponse
    {
        $lesson = DB::table('grammar_lessons')->where('id', $id)->where('status', 'published')->first();
        if (! $lesson) {
            return $this->notFound();
        }

        $content = DB::table('grammar_lesson_contents')->where('grammar_lesson_id', $id)
            ->where('status', 'published')->first();

        return response()->json([
            'id' => $lesson->id,
            'title' => $lesson->title,
            'summary' => $lesson->summary,
            'level' => $lesson->level,
            'contentSource' => $content ? [
                'type' => $content->type,
                'html' => $content->type === 'richtext' ? $sanitizer->clean(($content->format ?? 'html') === 'markdown' ? Str::markdown($content->html ?? '') : $content->html) : null,
                'url' => $content->type === 'officialAccount' ? $content->url : null,
                'title' => $content->title,
                'summary' => $content->summary,
            ] : null,
        ]);
    }

    public function questions(int $id): JsonResponse
    {
        $lesson = DB::table('grammar_lessons')->where('id', $id)->where('status', 'published')->first();
        if (! $lesson) {
            return $this->notFound();
        }

        $items = DB::table('grammar_questions')->where('grammar_lesson_id', $id)
            ->orderBy('sort_order')->get()
            ->map(fn ($question) => [
                'id' => $question->id,
                'sortOrder' => $question->sort_order,
                'kind' => $question->kind,
                'prompt' => $question->prompt,
                'options' => $question->options_json ? json_decode($question->options_json, true) : null,
            ]);

        return response()->json(['items' => $items]);
    }

    public function checkQuestion(Request $request, int $id): JsonResponse
    {
        $question = DB::table('grammar_questions as q')
            ->join('grammar_lessons as g', 'g.id', '=', 'q.grammar_lesson_id')
            ->where('q.id', $id)->where('g.status', 'published')
            ->select('q.*')->first();
        if (! $question) {
            return $this->notFound();
        }

        $data = $request->validate(['answer' => 'required']);
        if ($question->kind === 'choice') {
            $options = json_decode($question->options_json, true) ?: [];
            if (! is_int($data['answer']) || $data['answer'] < 0 || $data['answer'] >= count($options)) {
                return response()->json(['error' => ['code' => 'VALIDATION_ERROR', 'message' => '请选择有效选项']], 422);
            }
            $correct = $data['answer'] === (int) $question->answer;
            $correctAnswer = (int) $question->answer;
        } else {
            if (! is_string($data['answer'])) {
                return response()->json(['error' => ['code' => 'VALIDATION_ERROR', 'message' => '请输入答案']], 422);
            }
            $correct = mb_strtolower(trim($data['answer'])) === mb_strtolower(trim($question->answer));
            $correctAnswer = $question->answer;
        }

        return response()->json([
            'correct' => $correct,
            'correctAnswer' => $correctAnswer,
            'explanation' => $question->explanation,
        ]);
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => '语法内容不存在']], 404);
    }
}
