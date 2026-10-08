<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Database\Seeders\SampleReadingSeeder;
use Tests\TestCase;

class StudyContentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_published_reading_items_are_visible_and_paginate(): void
    {
        $this->reading('first', 'published', 1);
        $this->reading('second', 'published', 2);
        $this->reading('draft', 'draft', 3);

        $first = $this->getJson('/api/v1/reading-items?kind=article&limit=1')
            ->assertOk()->assertJsonPath('items.0.id', 'first')
            ->assertJsonCount(1, 'items')->json();

        $this->assertNotNull($first['nextCursor']);
        $this->getJson('/api/v1/reading-items?kind=article&limit=1&cursor='.urlencode($first['nextCursor']))
            ->assertOk()->assertJsonPath('items.0.id', 'second')
            ->assertJsonPath('nextCursor', null);
        $this->getJson('/api/v1/reading-items/draft')->assertNotFound();
        $this->getJson('/api/v1/reading-items?limit=100')->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_sample_reading_has_real_body_grammar_words_and_questions(): void
    {
        $this->seed(SampleReadingSeeder::class);
        $this->seed(SampleReadingSeeder::class);

        $this->getJson('/api/v1/reading-items?kind=article')->assertOk()
            ->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', 'sample-blue-umbrella');
        $this->getJson('/api/v1/reading-items/sample-blue-umbrella')->assertOk()
            ->assertJsonPath('body.0.sentences.0', 'On a rainy afternoon, Lily found a blue umbrella beside the bus stop.')
            ->assertJsonPath('wordCount', 8)->assertJsonPath('questionCount', 4);
        $this->getJson('/api/v1/reading-items/sample-blue-umbrella/grammar-analysis')->assertOk()
            ->assertJsonPath('type', 'richtext')->assertSee('even though');
        $this->getJson('/api/v1/reading-items/sample-blue-umbrella/words')->assertOk()
            ->assertJsonCount(8, 'items')->assertJsonPath('items.0.headword', 'kindness');
        $this->getJson('/api/v1/words/sample-word-kindness')->assertOk()
            ->assertJsonPath('contentSource.type', 'richtext');
        $this->getJson('/api/v1/reading-items/sample-blue-umbrella/questions')->assertOk()
            ->assertJsonCount(4, 'items')->assertDontSee('correctAnswer')->assertDontSee('explanation');
        $this->postJson('/api/v1/reading-questions/sample-umbrella-q1/check', ['answer' => 1])
            ->assertOk()->assertJsonPath('correct', true);
    }

    public function test_grammar_analysis_is_not_exposed_for_draft_article(): void
    {
        $this->reading('draft', 'draft', 1);
        $this->getJson('/api/v1/reading-items/draft/grammar-analysis')->assertNotFound();
    }

    public function test_question_answer_is_hidden_until_check(): void
    {
        $this->reading('first', 'published', 1);
        DB::table('reading_questions')->insert([
            'id' => 'question-1',
            'reading_item_id' => 'first',
            'sort_order' => 1,
            'kind' => 'choice',
            'prompt' => 'Where?',
            'options_json' => json_encode(['Home', 'School']),
            'answer' => '1',
            'explanation' => 'It says school.',
        ]);

        $response = $this->getJson('/api/v1/reading-items/first/questions')->assertOk();
        $response->assertDontSee('correctAnswer')->assertDontSee('explanation')->assertDontSee('"answer"');
        $this->postJson('/api/v1/reading-questions/question-1/check', ['answer' => 0])
            ->assertOk()->assertJsonPath('correct', false)->assertJsonPath('correctAnswer', 1);
        $this->postJson('/api/v1/reading-questions/question-1/check', ['answer' => 9])
            ->assertUnprocessable();
    }

    public function test_word_detail_requires_published_status(): void
    {
        DB::table('words')->insert([
            ['id' => 'word-1', 'headword' => 'umbrella', 'meaning' => '雨伞', 'status' => 'published'],
            ['id' => 'word-2', 'headword' => 'draft', 'meaning' => '草稿', 'status' => 'draft'],
        ]);

        $this->getJson('/api/v1/words/word-1')->assertOk()->assertJsonPath('headword', 'umbrella');
        $this->getJson('/api/v1/words/word-2')->assertNotFound();
    }

    public function test_grammar_question_and_puzzle_answers_are_not_exposed(): void
    {
        DB::table('content_sources')->insert(['id' => 'grammar-content', 'type' => 'richtext', 'html' => '<p>Past tense</p>']);
        DB::table('grammar_lessons')->insert([
            'id' => 'past', 'title' => 'Past tense', 'content_source_id' => 'grammar-content',
            'status' => 'published', 'sort_order' => 1,
        ]);
        DB::table('grammar_questions')->insert([
            'id' => 'grammar-question', 'grammar_lesson_id' => 'past', 'sort_order' => 1,
            'kind' => 'choice', 'prompt' => 'Choose one.',
            'options_json' => json_encode(['go', 'went']), 'answer' => '1',
        ]);
        DB::table('puzzle_sets')->insert(['id' => 'set-1', 'title' => 'A story', 'status' => 'published']);
        DB::table('puzzle_sentences')->insert([
            'id' => 'sentence-1', 'puzzle_set_id' => 'set-1', 'sort_order' => 1,
            'prompt_cn' => '我去了。', 'answer_text' => 'I went.',
            'tokens_json' => json_encode([
                ['id' => 'token-2', 'text' => 'went.'],
                ['id' => 'token-1', 'text' => 'I'],
            ]),
            'answer_token_ids_json' => json_encode(['token-1', 'token-2']),
        ]);

        $this->getJson('/api/v1/grammar-lessons/past/questions')->assertOk()
            ->assertDontSee('"answer"');
        $this->postJson('/api/v1/grammar-questions/grammar-question/check', ['answer' => 1])
            ->assertOk()->assertJsonPath('correct', true);
        $this->getJson('/api/v1/puzzle-sets/set-1')->assertOk()
            ->assertJsonPath('sentences.0.tokens.0.id', 'token-2')
            ->assertDontSee('answerText')->assertDontSee('answer_text');
    }

    public function test_published_chapters_use_the_same_reading_detail_and_skip_drafts(): void
    {
        DB::table('series')->insert(['id' => 'book', 'title' => 'A Book', 'status' => 'published']);
        foreach ([['chapter-1', 1, 'published'], ['chapter-2', 2, 'draft'], ['chapter-3', 3, 'published']] as [$id, $number, $status]) {
            DB::table('reading_items')->insert([
                'id' => $id, 'kind' => 'chapter', 'series_id' => 'book', 'chapter_no' => $number,
                'title' => $id, 'body_json' => json_encode([]), 'status' => $status,
            ]);
        }

        $this->getJson('/api/v1/series/book')->assertOk()->assertJsonCount(2, 'chapters')
            ->assertJsonPath('chapters.1.chapterNo', 3);
        $this->getJson('/api/v1/reading-items/chapter-1')->assertOk()
            ->assertJsonPath('kind', 'chapter')->assertJsonPath('nextChapterId', 'chapter-3');
        $this->getJson('/api/v1/reading-items/chapter-2')->assertNotFound();
    }

    private function reading(string $id, string $status, int $sortOrder): void
    {
        DB::table('reading_items')->insert([
            'id' => $id,
            'kind' => 'article',
            'title' => $id,
            'body_json' => json_encode([['sentences' => ['Hello.'], 'translation' => '你好。']]),
            'status' => $status,
            'sort_order' => $sortOrder,
        ]);
    }
}
