<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReadingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'kind' => 'sometimes|in:article,chapter',
            'level' => 'sometimes|string|max:32',
            'limit' => 'sometimes|integer|min:1|max:50',
            'cursor' => 'sometimes|string',
        ]);

        $query = DB::table('reading_items')->where('status', 'published');
        if (isset($filters['kind'])) {
            $query->where('kind', $filters['kind']);
        }
        if (isset($filters['level'])) {
            $query->where('level', $filters['level']);
        }

        $page = $query->orderBy('sort_order')->orderBy('id')->cursorPaginate($filters['limit'] ?? 20);

        return response()->json([
            'items' => $page->getCollection()->map(fn ($item) => $this->readingSummary($item))->all(),
            'nextCursor' => $page->nextCursor()?->encode(),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $item = $this->publishedReading($id);
        if (! $item) {
            return $this->notFound();
        }

        $result = $this->readingSummary($item) + [
            'body' => json_decode($item->body_json, true) ?: [],
            'wordCount' => DB::table('reading_words')->where('reading_item_id', $id)->count(),
            'questionCount' => DB::table('reading_questions')->where('reading_item_id', $id)->count(),
        ];

        if ($item->kind === 'chapter') {
            $chapters = DB::table('reading_items')
                ->where('series_id', $item->series_id)
                ->where('status', 'published')
                ->orderBy('chapter_no')
                ->pluck('id')
                ->all();
            $position = array_search($id, $chapters, true);
            $result['seriesId'] = $item->series_id;
            $result['chapterNo'] = $item->chapter_no;
            $result['previousChapterId'] = $position > 0 ? $chapters[$position - 1] : null;
            $result['nextChapterId'] = $position !== false ? ($chapters[$position + 1] ?? null) : null;
        }

        return response()->json($result);
    }

    public function words(string $id): JsonResponse
    {
        if (! $this->publishedReading($id)) {
            return $this->notFound();
        }

        $items = DB::table('reading_words as rw')
            ->join('words as w', 'w.id', '=', 'rw.word_id')
            ->where('rw.reading_item_id', $id)
            ->where('w.status', 'published')
            ->orderBy('rw.sort_order')
            ->get(['w.id', 'w.headword', 'w.ipa', 'w.part_of_speech', 'w.meaning', 'rw.sort_order'])
            ->map(fn ($word) => [
                'wordId' => $word->id,
                'headword' => $word->headword,
                'ipa' => $word->ipa,
                'partOfSpeech' => $word->part_of_speech,
                'meaning' => $word->meaning,
                'sortOrder' => $word->sort_order,
            ]);

        return response()->json(['items' => $items]);
    }

    public function grammarAnalysis(string $id): JsonResponse
    {
        $item = $this->publishedReading($id);
        if (! $item) {
            return $this->notFound();
        }

        if (! $item->grammar_source_id) {
            return $this->notFound();
        }

        $source = DB::table('content_sources')->where('id', $item->grammar_source_id)->first();
        if (! $source) {
            return $this->notFound();
        }

        return response()->json([
            'type' => $source->type,
            'html' => $source->type === 'richtext' ? $source->html : null,
            'url' => $source->type === 'officialAccount' ? $source->url : null,
            'title' => $source->title,
            'summary' => $source->summary,
        ]);
    }

    public function questions(string $id): JsonResponse
    {
        if (! $this->publishedReading($id)) {
            return $this->notFound();
        }

        $items = DB::table('reading_questions')->where('reading_item_id', $id)
            ->orderBy('sort_order')->get()
            ->map(fn ($question) => [
                'id' => $question->id,
                'sortOrder' => $question->sort_order,
                'questionType' => $question->question_type,
                'kind' => $question->kind,
                'prompt' => $question->prompt,
                'promptCn' => $question->prompt_cn,
                'options' => $question->options_json ? json_decode($question->options_json, true) : null,
            ]);

        return response()->json(['items' => $items]);
    }

    public function checkQuestion(Request $request, string $id): JsonResponse
    {
        $question = DB::table('reading_questions as q')
            ->join('reading_items as r', 'r.id', '=', 'q.reading_item_id')
            ->where('q.id', $id)->where('r.status', 'published')
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

    public function seriesIndex(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'limit' => 'sometimes|integer|min:1|max:50',
            'cursor' => 'sometimes|string',
        ]);
        $page = DB::table('series')->where('status', 'published')
            ->orderBy('sort_order')->orderBy('id')->cursorPaginate($filters['limit'] ?? 20);

        return response()->json([
            'items' => $page->getCollection()->map(fn ($series) => $this->seriesSummary($series))->all(),
            'nextCursor' => $page->nextCursor()?->encode(),
        ]);
    }

    public function seriesShow(string $id): JsonResponse
    {
        $series = DB::table('series')->where('id', $id)->where('status', 'published')->first();
        if (! $series) {
            return $this->notFound();
        }

        $chapters = DB::table('reading_items')
            ->where('series_id', $id)->where('status', 'published')
            ->orderBy('chapter_no')->get()
            ->map(fn ($chapter) => $this->readingSummary($chapter) + ['chapterNo' => $chapter->chapter_no]);

        return response()->json($this->seriesSummary($series) + ['chapters' => $chapters]);
    }

    private function readingSummary(object $item): array
    {
        return [
            'id' => $item->id,
            'kind' => $item->kind,
            'title' => $item->title,
            'translation' => $item->translation,
            'level' => $item->level,
            'minutes' => $item->minutes,
            'coverAsset' => $item->cover_asset,
        ];
    }

    private function seriesSummary(object $series): array
    {
        return [
            'id' => $series->id,
            'title' => $series->title,
            'translation' => $series->translation,
            'author' => $series->author,
            'level' => $series->level,
            'description' => $series->description,
            'coverAsset' => $series->cover_asset,
        ];
    }

    private function publishedReading(string $id): ?object
    {
        return DB::table('reading_items')->where('id', $id)->where('status', 'published')->first();
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => '内容不存在']], 404);
    }
}
