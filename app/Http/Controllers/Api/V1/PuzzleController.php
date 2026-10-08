<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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
                'tokens' => json_decode($sentence->tokens_json, true) ?: [],
            ]);

        return response()->json($this->summary($set) + ['sentences' => $sentences]);
    }

    private function summary(object $set): array
    {
        return [
            'id' => $set->id,
            'title' => $set->title,
            'subtitle' => $set->subtitle,
            'iconAsset' => $set->icon_asset,
            'sourceReadingItemId' => $set->source_reading_item_id,
        ];
    }
}
