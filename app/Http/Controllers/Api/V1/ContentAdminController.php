<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\HtmlContentSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ContentAdminController extends Controller
{
    public function readings(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'kind' => 'sometimes|in:article,chapter',
            'status' => 'sometimes|in:draft,published,archived',
            'limit' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = DB::table('reading_items');
        if (isset($filters['kind'])) $query->where('kind', $filters['kind']);
        if (isset($filters['status'])) $query->where('status', $filters['status']);

        $items = $query->orderByDesc('updated_at')->orderByDesc('id')
            ->limit($filters['limit'] ?? 50)->get();

        return response()->json(['items' => $items->map(fn (object $item): array => [
            'id' => (int) $item->id,
            'kind' => $item->kind,
            'title' => $item->title,
            'translation' => $item->translation,
            'level' => $item->level,
            'status' => $item->status,
            'updatedAt' => $item->updated_at,
        ])]);
    }

    public function createReading(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slug' => 'nullable|string|max:100|unique:reading_items,slug',
            'title' => 'required|string|max:255',
            'translation' => 'nullable|string|max:255',
            'level' => 'nullable|string|max:32',
            'minutes' => 'nullable|integer|min:1|max:65535',
            'coverAsset' => 'nullable|string|max:255',
            'body' => 'required|array|min:1',
            'body.*.sentences' => 'required|array|min:1',
            'body.*.sentences.*' => 'required|string|max:5000',
            'body.*.translation' => 'required|string|max:5000',
            'rightsType' => 'nullable|string|max:32',
            'rightsNote' => 'nullable|string|max:10000',
            'sortOrder' => 'sometimes|integer|min:0|max:4294967295',
        ]);

        $id = DB::table('reading_items')->insertGetId([
            'slug' => $data['slug'] ?? null,
            'kind' => 'article',
            'title' => $data['title'],
            'translation' => $data['translation'] ?? null,
            'level' => $data['level'] ?? null,
            'minutes' => $data['minutes'] ?? null,
            'cover_asset' => $data['coverAsset'] ?? null,
            'body_json' => json_encode($data['body'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'status' => 'draft',
            'sort_order' => $data['sortOrder'] ?? 0,
            'rights_type' => $data['rightsType'] ?? null,
            'rights_note' => $data['rightsNote'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json($this->readingDetail($id), 201);
    }

    public function updateReading(Request $request, int $id): JsonResponse
    {
        $reading = DB::table('reading_items')->where('id', $id)->first();
        if (! $reading) return $this->notFound();

        $data = $request->validate([
            'slug' => ['sometimes', 'nullable', 'string', 'max:100', Rule::unique('reading_items', 'slug')->ignore($id)],
            'title' => 'sometimes|required|string|max:255',
            'translation' => 'sometimes|nullable|string|max:255',
            'level' => 'sometimes|nullable|string|max:32',
            'minutes' => 'sometimes|nullable|integer|min:1|max:65535',
            'coverAsset' => 'sometimes|nullable|string|max:255',
            'body' => 'sometimes|required|array|min:1',
            'body.*.sentences' => 'required_with:body|array|min:1',
            'body.*.sentences.*' => 'required_with:body|string|max:5000',
            'body.*.translation' => 'required_with:body|string|max:5000',
            'rightsType' => 'sometimes|nullable|string|max:32',
            'rightsNote' => 'sometimes|nullable|string|max:10000',
            'sortOrder' => 'sometimes|integer|min:0|max:4294967295',
        ]);

        $columns = [
            'slug' => 'slug', 'title' => 'title', 'translation' => 'translation', 'level' => 'level',
            'minutes' => 'minutes', 'coverAsset' => 'cover_asset', 'rightsType' => 'rights_type',
            'rightsNote' => 'rights_note', 'sortOrder' => 'sort_order',
        ];
        $update = [];
        foreach ($columns as $input => $column) {
            if (array_key_exists($input, $data)) $update[$column] = $data[$input];
        }
        if (array_key_exists('body', $data)) {
            $update['body_json'] = json_encode($data['body'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
        if ($update !== []) {
            $update['updated_at'] = now();
            DB::table('reading_items')->where('id', $id)->update($update);
        }

        return response()->json($this->readingDetail($id));
    }

    public function readingDetail(int $id): array|JsonResponse
    {
        $reading = DB::table('reading_items')->where('id', $id)->first();
        if (! $reading) return $this->notFound();

        $analysis = DB::table('reading_analyses')->where('reading_item_id', $id)->first();
        $words = DB::table('reading_words as rw')->join('words as w', 'w.id', '=', 'rw.word_id')
            ->where('rw.reading_item_id', $id)->orderBy('rw.sort_order')
            ->get(['w.id', 'w.headword', 'rw.sort_order']);

        return [
            'id' => (int) $reading->id,
            'slug' => $reading->slug,
            'kind' => $reading->kind,
            'title' => $reading->title,
            'translation' => $reading->translation,
            'level' => $reading->level,
            'minutes' => $reading->minutes,
            'coverAsset' => $reading->cover_asset,
            'sortOrder' => (int) $reading->sort_order,
            'body' => json_decode($reading->body_json, true) ?: [],
            'status' => $reading->status,
            'rightsType' => $reading->rights_type,
            'rightsNote' => $reading->rights_note,
            'analysis' => $analysis ? [
                'id' => (int) $analysis->id, 'type' => $analysis->type, 'html' => $analysis->html,
                'url' => $analysis->url, 'title' => $analysis->title, 'summary' => $analysis->summary,
                'status' => $analysis->status,
            ] : null,
            'words' => $words->map(fn (object $word): array => [
                'id' => (int) $word->id, 'headword' => $word->headword, 'sortOrder' => (int) $word->sort_order,
            ]),
        ];
    }

    public function saveAnalysis(Request $request, int $id): JsonResponse
    {
        if (! DB::table('reading_items')->where('id', $id)->exists()) return $this->notFound();
        $data = $request->validate([
            'type' => 'required|in:richtext,officialAccount',
            'html' => 'required_if:type,richtext|nullable|string|max:1000000',
            'url' => 'required_if:type,officialAccount|nullable|url|max:2048',
            'title' => 'nullable|string|max:255',
            'summary' => 'nullable|string|max:10000',
        ]);
        if ($data['type'] === 'richtext') $data['html'] = app(HtmlContentSanitizer::class)->clean($data['html'] ?? '');
        if ($data['type'] === 'richtext' && trim(strip_tags($data['html'])) === '') {
            return response()->json(['error' => ['code' => 'VALIDATION_ERROR', 'message' => '富文本清理后没有可显示的内容']], 422);
        }

        $now = now();
        $analysisStatus = DB::table('reading_items')->where('id', $id)->value('status') === 'published' ? 'published' : 'draft';
        DB::table('reading_analyses')->updateOrInsert(['reading_item_id' => $id], [
            'type' => $data['type'], 'html' => $data['html'] ?? null, 'url' => $data['url'] ?? null,
            'title' => $data['title'] ?? null, 'summary' => $data['summary'] ?? null,
            'status' => $analysisStatus, 'updated_at' => $now,
        ] + (DB::table('reading_analyses')->where('reading_item_id', $id)->exists() ? [] : ['created_at' => $now]));

        return response()->json($this->readingDetail($id));
    }

    public function syncReadingWords(Request $request, int $id): JsonResponse
    {
        if (! DB::table('reading_items')->where('id', $id)->exists()) return $this->notFound();
        $data = $request->validate([
            'wordIds' => 'present|array|max:500',
            'wordIds.*' => 'required|integer|distinct|exists:words,id',
        ]);

        DB::transaction(function () use ($id, $data): void {
            DB::table('reading_words')->where('reading_item_id', $id)->delete();
            $rows = [];
            foreach ($data['wordIds'] as $sortOrder => $wordId) {
                $rows[] = ['reading_item_id' => $id, 'word_id' => $wordId, 'sort_order' => $sortOrder + 1];
            }
            if ($rows !== []) DB::table('reading_words')->insert($rows);
        });

        return response()->json($this->readingDetail($id));
    }

    public function publishReading(int $id): JsonResponse
    {
        $reading = DB::table('reading_items')->where('id', $id)->first();
        if (! $reading) return $this->notFound();
        if (! $reading->rights_type || ! $reading->rights_note || ! $reading->body_json) {
            return response()->json(['error' => ['code' => 'CONTENT_INCOMPLETE', 'message' => '发布前请补齐正文和版权来源说明']], 422);
        }

        DB::transaction(function () use ($id): void {
            DB::table('reading_items')->where('id', $id)->update(['status' => 'published', 'updated_at' => now()]);
            DB::table('reading_analyses')->where('reading_item_id', $id)->update(['status' => 'published', 'updated_at' => now()]);
        });
        return response()->json($this->readingDetail($id));
    }

    public function unpublishReading(int $id): JsonResponse
    {
        if (! DB::table('reading_items')->where('id', $id)->exists()) return $this->notFound();
        DB::transaction(function () use ($id): void {
            DB::table('reading_items')->where('id', $id)->update(['status' => 'draft', 'updated_at' => now()]);
            DB::table('reading_analyses')->where('reading_item_id', $id)->update(['status' => 'draft', 'updated_at' => now()]);
        });

        return response()->json($this->readingDetail($id));
    }

    public function words(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => 'sometimes|in:draft,published,archived',
            'q' => 'sometimes|string|max:100',
            'limit' => 'sometimes|integer|min:1|max:100',
        ]);
        $query = DB::table('words');
        if (isset($filters['status'])) $query->where('status', $filters['status']);
        if (isset($filters['q'])) $query->where('headword', 'like', '%'.$filters['q'].'%');
        $items = $query->orderBy('headword')->limit($filters['limit'] ?? 50)->get();

        return response()->json(['items' => $items->map(fn (object $word): array => [
            'id' => (int) $word->id, 'headword' => $word->headword, 'ipa' => $word->ipa,
            'partOfSpeech' => $word->part_of_speech, 'meaning' => $word->meaning, 'status' => $word->status,
        ])]);
    }

    public function showWord(int $id): JsonResponse
    {
        if (! DB::table('words')->where('id', $id)->exists()) return $this->notFound();
        return response()->json($this->wordDetail($id));
    }

    public function createWord(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slug' => 'nullable|string|max:100|unique:words,slug',
            'headword' => 'required|string|max:255', 'ipa' => 'nullable|string|max:255',
            'partOfSpeech' => 'nullable|string|max:64', 'meaning' => 'required|string|max:255',
            'frequencyLabel' => 'nullable|string|max:255', 'audioAsset' => 'nullable|string|max:255',
        ]);
        $id = DB::table('words')->insertGetId([
            'slug' => $data['slug'] ?? null, 'headword' => $data['headword'], 'ipa' => $data['ipa'] ?? null,
            'part_of_speech' => $data['partOfSpeech'] ?? null, 'meaning' => $data['meaning'],
            'frequency_label' => $data['frequencyLabel'] ?? null, 'audio_asset' => $data['audioAsset'] ?? null,
            'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return response()->json($this->wordDetail($id), 201);
    }

    public function updateWord(Request $request, int $id): JsonResponse
    {
        if (! DB::table('words')->where('id', $id)->exists()) return $this->notFound();
        $data = $request->validate([
            'slug' => ['sometimes', 'nullable', 'string', 'max:100', Rule::unique('words', 'slug')->ignore($id)],
            'headword' => 'sometimes|required|string|max:255', 'ipa' => 'sometimes|nullable|string|max:255',
            'partOfSpeech' => 'sometimes|nullable|string|max:64', 'meaning' => 'sometimes|required|string|max:255',
            'frequencyLabel' => 'sometimes|nullable|string|max:255', 'audioAsset' => 'sometimes|nullable|string|max:255',
        ]);
        $columns = ['slug' => 'slug', 'headword' => 'headword', 'ipa' => 'ipa', 'partOfSpeech' => 'part_of_speech', 'meaning' => 'meaning', 'frequencyLabel' => 'frequency_label', 'audioAsset' => 'audio_asset'];
        $update = [];
        foreach ($columns as $input => $column) if (array_key_exists($input, $data)) $update[$column] = $data[$input];
        if ($update !== []) {
            $update['updated_at'] = now();
            DB::table('words')->where('id', $id)->update($update);
        }

        return response()->json($this->wordDetail($id));
    }

    public function saveWordDetail(Request $request, int $id): JsonResponse
    {
        if (! DB::table('words')->where('id', $id)->exists()) return $this->notFound();
        $data = $request->validate([
            'type' => 'required|in:richtext,officialAccount',
            'html' => 'required_if:type,richtext|nullable|string|max:1000000',
            'url' => 'required_if:type,officialAccount|nullable|url|max:2048',
            'title' => 'nullable|string|max:255', 'summary' => 'nullable|string|max:10000',
        ]);
        if ($data['type'] === 'richtext') $data['html'] = app(HtmlContentSanitizer::class)->clean($data['html'] ?? '');
        if ($data['type'] === 'richtext' && trim(strip_tags($data['html'])) === '') {
            return response()->json(['error' => ['code' => 'VALIDATION_ERROR', 'message' => '富文本清理后没有可显示的内容']], 422);
        }
        $now = now();
        $exists = DB::table('word_details')->where('word_id', $id)->exists();
        $detailStatus = DB::table('words')->where('id', $id)->value('status') === 'published' ? 'published' : 'draft';
        DB::table('word_details')->updateOrInsert(['word_id' => $id], [
            'type' => $data['type'], 'html' => $data['html'] ?? null, 'url' => $data['url'] ?? null,
            'title' => $data['title'] ?? null, 'summary' => $data['summary'] ?? null,
            'status' => $detailStatus, 'updated_at' => $now,
        ] + ($exists ? [] : ['created_at' => $now]));

        return response()->json($this->wordDetail($id));
    }

    public function publishWord(int $id): JsonResponse
    {
        $word = DB::table('words')->where('id', $id)->first();
        if (! $word) return $this->notFound();
        $detail = DB::table('word_details')->where('word_id', $id)->first();
        if (! $detail || ($detail->type === 'richtext' && trim(strip_tags((string) $detail->html)) === '') || ($detail->type === 'officialAccount' && ! $detail->url)) {
            return response()->json(['error' => ['code' => 'CONTENT_INCOMPLETE', 'message' => '请先添加单词详解内容再发布']], 422);
        }

        DB::transaction(function () use ($id): void {
            DB::table('words')->where('id', $id)->update(['status' => 'published', 'updated_at' => now()]);
            DB::table('word_details')->where('word_id', $id)->update(['status' => 'published', 'updated_at' => now()]);
        });
        return response()->json($this->wordDetail($id));
    }

    public function unpublishWord(int $id): JsonResponse
    {
        if (! DB::table('words')->where('id', $id)->exists()) return $this->notFound();
        DB::transaction(function () use ($id): void {
            DB::table('words')->where('id', $id)->update(['status' => 'draft', 'updated_at' => now()]);
            DB::table('word_details')->where('word_id', $id)->update(['status' => 'draft', 'updated_at' => now()]);
        });
        return response()->json($this->wordDetail($id));
    }

    public function wordDetail(int $id): array|JsonResponse
    {
        $word = DB::table('words')->where('id', $id)->first();
        if (! $word) return $this->notFound();
        $detail = DB::table('word_details')->where('word_id', $id)->first();

        return [
            'id' => (int) $word->id, 'slug' => $word->slug, 'headword' => $word->headword,
            'ipa' => $word->ipa, 'partOfSpeech' => $word->part_of_speech, 'meaning' => $word->meaning,
            'frequencyLabel' => $word->frequency_label, 'audioAsset' => $word->audio_asset,
            'status' => $word->status,
            'detail' => $detail ? [
                'id' => (int) $detail->id, 'type' => $detail->type, 'html' => $detail->html,
                'url' => $detail->url, 'title' => $detail->title, 'summary' => $detail->summary,
                'status' => $detail->status,
            ] : null,
        ];
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => '内容不存在']], 404);
    }
}
