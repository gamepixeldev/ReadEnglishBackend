<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class WordSensePresenter
{
    /** @param iterable<int|string> $wordIds @return array<int, array<int, array{partOfSpeech:?string,meaning:string}>> */
    public static function many(iterable $wordIds): array
    {
        $ids = collect($wordIds)->map(fn ($id): int => (int) $id)->unique()->values();
        if ($ids->isEmpty()) return [];

        return DB::table('word_senses')->whereIn('word_id', $ids)->orderBy('sort_order')->orderBy('id')
            ->get(['word_id', 'part_of_speech', 'meaning'])->groupBy('word_id')
            ->map(fn ($rows): array => $rows->map(fn ($row): array => [
                'partOfSpeech' => $row->part_of_speech,
                'meaning' => $row->meaning,
            ])->values()->all())->all();
    }

    /** @param array<int, array{partOfSpeech:?string,meaning:string}> $senses @return array{partOfSpeech:?string,meaning:string,senses:array} */
    public static function summary(array $senses, ?string $fallbackPartOfSpeech = null, ?string $fallbackMeaning = null): array
    {
        if ($senses === [] && $fallbackMeaning !== null) {
            $senses = [['partOfSpeech' => $fallbackPartOfSpeech, 'meaning' => $fallbackMeaning]];
        }
        $parts = collect($senses)->pluck('partOfSpeech')->filter()->unique()->values();

        return [
            'partOfSpeech' => $parts->isNotEmpty() ? $parts->implode(' / ') : $fallbackPartOfSpeech,
            'meaning' => $senses !== [] ? collect($senses)->pluck('meaning')->implode('；') : ($fallbackMeaning ?? ''),
            'senses' => $senses,
        ];
    }
}
