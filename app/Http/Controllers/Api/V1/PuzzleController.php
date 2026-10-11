<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ReadingCover;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PuzzleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'source' => 'sometimes|in:all,independent,reading',
            'limit' => 'sometimes|integer|min:1|max:50',
            'cursor' => 'sometimes|string',
        ]);

        $query = DB::table('puzzle_sets')->where('status', 'published');
        if (($filters['source'] ?? 'all') === 'reading') {
            $query->whereNotNull('source_reading_item_id');
        } elseif (($filters['source'] ?? 'all') === 'independent') {
            $query->whereNull('source_reading_item_id');
        }
        $page = $query->orderBy('sort_order')->orderBy('id')->cursorPaginate($filters['limit'] ?? 20);

        return response()->json([
            'items' => $page->getCollection()->map(fn ($set) => $this->summary($set))->all(),
            'nextCursor' => $page->nextCursor()?->encode(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $set = DB::table('puzzle_sets')->where('id', $id)->where('status', 'published')->first();
        if (! $set) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => '拼句内容不存在']], 404);
        }

        $sentences = DB::table('puzzle_sentences')->where('puzzle_set_id', $id)
            ->orderBy('sort_order')->get()
            ->map(fn ($sentence) => [
                'id' => $sentence->id,
                'sortOrder' => $sentence->sort_order,
                'promptCn' => $sentence->prompt_cn,
                'answerText' => $sentence->answer_text,
                'tokens' => json_decode($sentence->tokens_json, true) ?: [],
            ]);

        return response()->json($this->summary($set) + ['sentences' => $sentences]);
    }

    public function check(Request $request, int $id): JsonResponse
    {
        $input = $request->validate(['tokenIds' => ['required', 'array', 'min:1'], 'tokenIds.*' => ['required', 'integer']]);
        $sentence = DB::table('puzzle_sentences as p')
            ->join('puzzle_sets as s', 's.id', '=', 'p.puzzle_set_id')
            ->where('p.id', $id)->where('s.status', 'published')->select('p.*')->first();
        if (! $sentence) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => '句子不存在']], 404);
        }
        $answerIds = json_decode($sentence->answer_token_ids_json, true) ?: [];
        $submitted = $input['tokenIds'];
        $sortedSubmitted = $submitted;
        $sortedAnswer = $answerIds;
        sort($sortedSubmitted);
        sort($sortedAnswer);
        if (count($submitted) !== count($answerIds) || $sortedSubmitted !== $sortedAnswer) {
            return response()->json(['error' => ['code' => 'INVALID_TOKENS', 'message' => '请使用全部词块']], 422);
        }

        return response()->json([
            'correct' => $submitted === $answerIds,
            'positions' => array_map(fn ($id, $index) => $id === $answerIds[$index], $submitted, array_keys($submitted)),
        ]);
    }

    private function summary(object $set): array
    {
        return [
            'id' => $set->id,
            'title' => $set->title,
            'subtitle' => $set->subtitle,
            'coverUrl' => ReadingCover::url($set->cover_asset),
            'iconAsset' => $set->icon_asset,
            'sourceReadingItemId' => $set->source_reading_item_id,
            'sentenceCount' => DB::table('puzzle_sentences')->where('puzzle_set_id', $set->id)->count(),
        ];
    }
}
