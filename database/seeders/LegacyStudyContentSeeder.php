<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LegacyStudyContentSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(file_get_contents(database_path('data/study-fixtures.json')), true, 512, JSON_THROW_ON_ERROR);
        DB::transaction(function () use ($data): void {
            $wordIds = $this->words($data['words']);
            $readingIds = $this->readings($data['articles'], $data['externalSeries'], $wordIds);
            $this->grammar($data['grammar']);
            $this->puzzles($data['puzzleSets'], $readingIds);
        });
    }

    private function words(array $words): array
    {
        $ids = [];
        foreach ($words as $word) {
            $headword = $word['word'];
            $id = DB::table('words')->where('headword', $headword)->orderBy('id')->value('id');
            if (! $id) {
                $id = DB::table('words')->insertGetId([
                    'slug' => 'fixture-word-'.$headword,
                    'headword' => $headword,
                    'ipa' => $word['ipa'],
                    'part_of_speech' => $word['pos'],
                    'meaning' => $word['meaning'],
                    'frequency_label' => $word['frequency'],
                    'status' => 'published',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $ids[$headword] = $id;
            if (! DB::table('word_details')->where('word_id', $id)->exists()) {
                DB::table('word_details')->insert([
                    'word_id' => $id,
                    'type' => $word['contentSource']['type'],
                    'html' => $word['contentSource']['html'] ?? null,
                    'url' => $word['contentSource']['url'] ?? null,
                    'title' => $word['contentSource']['title'] ?? null,
                    'summary' => $word['contentSource']['summary'] ?? null,
                    'status' => 'published',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        return $ids;
    }

    private function readings(array $articles, array $series, array $wordIds): array
    {
        $readingIds = [];
        foreach ($articles as $index => $article) {
            $slug = $article['id'] === 'umbrella' ? 'sample-blue-umbrella' : 'fixture-article-'.$article['id'];
            $id = DB::table('reading_items')->where('slug', $slug)->value('id');
            if (! $id) {
                $id = DB::table('reading_items')->insertGetId([
                    'slug' => $slug, 'kind' => 'article',
                    'title' => $article['title'], 'translation' => $article['translation'],
                    'level' => $article['level'], 'minutes' => $article['minutes'],
                    'body_json' => $this->body($article), 'status' => 'published',
                    'sort_order' => $index + 1, 'rights_type' => 'original',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $readingIds[$article['id']] = $id;
            $this->readingDetails($id, $article, $wordIds);
        }
        foreach ($series as $seriesIndex => $book) {
            $seriesId = DB::table('series')->where('title', $book['title'])->value('id');
            if (! $seriesId) {
                $seriesId = DB::table('series')->insertGetId([
                    'title' => $book['title'], 'translation' => $book['translation'],
                    'author' => $book['author'], 'level' => $book['level'],
                    'description' => $book['description'], 'status' => 'published',
                    'sort_order' => $seriesIndex + 1, 'rights_type' => 'original',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            foreach ($book['chapters'] as $chapterIndex => $chapter) {
                $slug = 'fixture-chapter-'.$book['id'].'-'.($chapterIndex + 1);
                $id = DB::table('reading_items')->where('slug', $slug)->value('id');
                if (! $id) {
                    $id = DB::table('reading_items')->insertGetId([
                        'slug' => $slug, 'kind' => 'chapter', 'series_id' => $seriesId,
                        'chapter_no' => $chapterIndex + 1, 'title' => $chapter['title'],
                        'level' => $book['level'], 'minutes' => $chapter['minutes'],
                        'body_json' => $this->body($chapter), 'status' => 'published',
                        'sort_order' => $chapterIndex + 1, 'rights_type' => 'original',
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                $this->readingDetails($id, $chapter, $wordIds);
            }
        }

        return $readingIds;
    }

    private function body(array $reading): string
    {
        return json_encode(array_map(fn ($sentences, $index) => [
            'sentences' => $sentences,
            'translation' => $reading['translations'][$index] ?? '',
            'sentenceTranslations' => $reading['sentenceTranslations'][$index] ?? [],
        ], $reading['paragraphs'], array_keys($reading['paragraphs'])), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function readingDetails(int $id, array $reading, array $wordIds): void
    {
        foreach ($reading['words'] ?? [] as $index => $headword) {
            if (isset($wordIds[$headword])) {
                DB::table('reading_words')->insertOrIgnore([
                    'reading_item_id' => $id, 'word_id' => $wordIds[$headword], 'sort_order' => $index + 1,
                ]);
            }
        }
        if (! DB::table('reading_analyses')->where('reading_item_id', $id)->exists()) {
            $source = $reading['grammarContentSource'] ?? null;
            if (! $source && ! empty($reading['grammarNotes'])) {
                $parts = [];
                foreach ($reading['grammarNotes'] as $note) {
                    $parts[] = '<h3>'.e($note['title']).'</h3><p>'.e($note['explanation']).'</p>';
                    foreach ($note['examples'] ?? [] as $example) {
                        $parts[] = '<p><strong>'.e($example['en']).'</strong><br>'.e($example['cn']).'</p>';
                    }
                }
                $source = ['type' => 'richtext', 'html' => implode('', $parts)];
            }
            if ($source) {
                DB::table('reading_analyses')->insert([
                    'reading_item_id' => $id, 'type' => $source['type'],
                    'html' => $source['html'] ?? null, 'url' => $source['url'] ?? null,
                    'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
        foreach ($reading['questions'] ?? [] as $index => $question) {
            if (DB::table('reading_questions')->where('reading_item_id', $id)->where('sort_order', $index + 1)->exists()) {
                continue;
            }
            DB::table('reading_questions')->insert([
                'reading_item_id' => $id, 'sort_order' => $index + 1,
                'question_type' => $question['type'] ?? null,
                'kind' => $question['kind'] ?? 'choice',
                'prompt' => $question['prompt'], 'prompt_cn' => $question['cn'] ?? null,
                'options_json' => json_encode($question['options'] ?? [], JSON_UNESCAPED_UNICODE),
                'answer' => (string) ($question['answer'] ?? ''),
                'explanation' => $question['explanation'] ?? null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function grammar(array $lessons): void
    {
        foreach ($lessons as $index => $lesson) {
            $id = DB::table('grammar_lessons')->where('title', $lesson['title'])->value('id');
            if (! $id) {
                $id = DB::table('grammar_lessons')->insertGetId([
                    'title' => $lesson['title'], 'summary' => $lesson['summary'],
                    'level' => $lesson['level'], 'status' => 'published',
                    'sort_order' => $index + 1, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            if (! DB::table('grammar_lesson_contents')->where('grammar_lesson_id', $id)->exists()) {
                $source = $lesson['contentSource'];
                DB::table('grammar_lesson_contents')->insert([
                    'grammar_lesson_id' => $id, 'type' => $source['type'],
                    'html' => $source['html'] ?? null, 'url' => $source['url'] ?? null,
                    'title' => $source['title'] ?? null, 'summary' => $source['summary'] ?? null,
                    'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            if (! DB::table('grammar_questions')->where('grammar_lesson_id', $id)->exists()) {
                DB::table('grammar_questions')->insert([
                    'grammar_lesson_id' => $id, 'sort_order' => 1, 'kind' => 'choice',
                    'prompt' => $lesson['quiz']['prompt'],
                    'options_json' => json_encode($lesson['quiz']['options'], JSON_UNESCAPED_UNICODE),
                    'answer' => (string) $lesson['quiz']['answer'],
                    'explanation' => $lesson['quiz']['explanation'],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    private function puzzles(array $sets, array $readingIds): void
    {
        foreach ($sets as $index => $set) {
            $id = DB::table('puzzle_sets')->where('title', $set['title'])->value('id');
            if (! $id) {
                $id = DB::table('puzzle_sets')->insertGetId([
                    'title' => $set['title'], 'subtitle' => $set['subtitle'],
                    'icon_asset' => $set['icon'],
                    'source_reading_item_id' => $readingIds[$set['sourceArticleId'] ?? ''] ?? null,
                    'status' => 'published', 'sort_order' => $index + 1,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            foreach ($set['items'] as $sentenceIndex => $item) {
                if (DB::table('puzzle_sentences')->where('puzzle_set_id', $id)->where('sort_order', $sentenceIndex + 1)->exists()) {
                    continue;
                }
                $tokens = preg_split('/\s+/u', trim($item['answer']));
                $bank = array_map(fn ($text, $position) => ['id' => $position + 1, 'text' => $text], $tokens, array_keys($tokens));
                DB::table('puzzle_sentences')->insert([
                    'puzzle_set_id' => $id, 'sort_order' => $sentenceIndex + 1,
                    'prompt_cn' => $item['cn'], 'answer_text' => $item['answer'],
                    'tokens_json' => json_encode($bank, JSON_UNESCAPED_UNICODE),
                    'answer_token_ids_json' => json_encode(range(1, count($bank))),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }
}
