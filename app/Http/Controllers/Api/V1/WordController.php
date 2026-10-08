<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class WordController extends Controller
{
    public function show(int $id): JsonResponse
    {
        $word = DB::table('words')->where('id', $id)->where('status', 'published')->first();
        if (! $word) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => '单词不存在']], 404);
        }

        $source = DB::table('word_details')->where('word_id', $id)->where('status', 'published')->first();

        return response()->json([
            'id' => $word->id,
            'headword' => $word->headword,
            'ipa' => $word->ipa,
            'partOfSpeech' => $word->part_of_speech,
            'meaning' => $word->meaning,
            'frequencyLabel' => $word->frequency_label,
            'audioAsset' => $word->audio_asset,
            'contentSource' => $source ? [
                'type' => $source->type,
                'html' => $source->type === 'richtext' ? $source->html : null,
                'url' => $source->type === 'officialAccount' ? $source->url : null,
                'title' => $source->title,
                'summary' => $source->summary,
            ] : null,
        ]);
    }
}
