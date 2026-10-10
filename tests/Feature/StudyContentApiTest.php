<?php

namespace Tests\Feature;

use Database\Seeders\SampleReadingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudyContentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_published_reading_items_are_visible_and_paginate(): void
    {
        $firstId = $this->reading('first', 'published', 1);
        $secondId = $this->reading('second', 'published', 2);
        $draftId = $this->reading('draft', 'draft', 3);

        $first = $this->getJson('/api/v1/reading-items?kind=article&limit=1')
            ->assertOk()->assertJsonPath('items.0.id', $firstId)
            ->assertJsonCount(1, 'items')->json();

        $this->assertNotNull($first['nextCursor']);
        $this->getJson('/api/v1/reading-items?kind=article&limit=1&cursor='.urlencode($first['nextCursor']))
            ->assertOk()->assertJsonPath('items.0.id', $secondId)
            ->assertJsonPath('nextCursor', null);
        $this->getJson('/api/v1/reading-items/'.$draftId)->assertNotFound();
        $this->getJson('/api/v1/reading-items/first')->assertNotFound();
        $this->getJson('/api/v1/reading-items?limit=100')->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_sample_reading_has_real_body_independent_analysis_words_and_questions(): void
    {
        $this->seed(SampleReadingSeeder::class);
        $this->seed(SampleReadingSeeder::class);
        $articleId = DB::table('reading_items')->where('slug', 'sample-blue-umbrella')->value('id');
        $wordId = DB::table('words')->where('slug', 'sample-word-kindness')->value('id');
        $questionId = DB::table('reading_questions')->where('reading_item_id', $articleId)
            ->where('sort_order', 1)->value('id');

        $this->assertIsInt($articleId);
        $this->assertIsInt($wordId);
        $this->getJson('/api/v1/reading-items?kind=article')->assertOk()
            ->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $articleId);
        $this->getJson('/api/v1/reading-items/'.$articleId)->assertOk()
            ->assertJsonPath('body.0.sentences.0', 'On a rainy afternoon, Lily found a blue umbrella beside the bus stop.')
            ->assertJsonPath('wordCount', 8)->assertJsonPath('questionCount', 4)
            ->assertJsonPath('hasGrammarAnalysis', true);
        $this->getJson('/api/v1/reading-items/'.$articleId.'/grammar-analysis')->assertOk()
            ->assertJsonPath('type', 'richtext')->assertSee('even though');
        $this->getJson('/api/v1/reading-items/'.$articleId.'/words')->assertOk()
            ->assertJsonCount(8, 'items')->assertJsonPath('items.0.wordId', $wordId);
        $this->getJson('/api/v1/words/'.$wordId)->assertOk()
            ->assertJsonPath('contentSource.type', 'richtext');
        $this->getJson('/api/v1/reading-items/'.$articleId.'/questions')->assertOk()
            ->assertJsonCount(4, 'items')->assertDontSee('correctAnswer')->assertDontSee('explanation');
        $this->postJson('/api/v1/reading-questions/'.$questionId.'/check', ['answer' => 1])
            ->assertOk()->assertJsonPath('correct', true);
        $this->assertSame(1, DB::table('reading_analyses')->count());
        $this->assertSame(8, DB::table('word_details')->count());
    }

    public function test_reading_analysis_can_be_bound_after_article_creation(): void
    {
        $articleId = $this->reading('later', 'published', 1);
        $this->getJson('/api/v1/reading-items/'.$articleId)->assertJsonPath('hasGrammarAnalysis', false);
        $this->getJson('/api/v1/reading-items/'.$articleId.'/grammar-analysis')->assertNotFound();

        DB::table('reading_analyses')->insert([
            'reading_item_id' => $articleId,
            'type' => 'richtext',
            'html' => '<p>解析</p>',
            'status' => 'published',
        ]);
        $this->getJson('/api/v1/reading-items/'.$articleId)->assertJsonPath('hasGrammarAnalysis', true);
        $this->getJson('/api/v1/reading-items/'.$articleId.'/grammar-analysis')->assertOk()
            ->assertJsonPath('html', '<p>解析</p>');
    }

    public function test_reading_analysis_can_return_richtext_and_official_account_link_together(): void
    {
        $articleId = $this->reading('hybrid-analysis', 'published', 1);
        DB::table('reading_analyses')->insert([
            'reading_item_id' => $articleId,
            'type' => 'richtext',
            'format' => 'html',
            'html' => '<p>小程序内语法讲解</p>',
            'url' => 'https://mp.weixin.qq.com/s/example',
            'title' => '公众号原文标题',
            'summary' => '原文摘要',
            'status' => 'published',
        ]);

        $this->getJson('/api/v1/reading-items/'.$articleId.'/grammar-analysis')->assertOk()
            ->assertJsonPath('html', '<p>小程序内语法讲解</p>')
            ->assertJsonPath('url', 'https://mp.weixin.qq.com/s/example')
            ->assertJsonPath('title', '公众号原文标题');
    }

    public function test_analysis_of_draft_article_is_not_exposed(): void
    {
        $draftId = $this->reading('draft', 'draft', 1);
        DB::table('reading_analyses')->insert([
            'reading_item_id' => $draftId,
            'type' => 'richtext',
            'html' => '<p>草稿解析</p>',
            'status' => 'published',
        ]);
        $this->getJson('/api/v1/reading-items/'.$draftId.'/grammar-analysis')->assertNotFound();
    }

    public function test_question_answer_is_hidden_until_check(): void
    {
        $articleId = $this->reading('first', 'published', 1);
        $questionId = DB::table('reading_questions')->insertGetId([
            'reading_item_id' => $articleId,
            'sort_order' => 1,
            'kind' => 'choice',
            'prompt' => 'Where?',
            'options_json' => json_encode(['Home', 'School']),
            'answer' => '1',
            'explanation' => 'It says school.',
        ]);

        $this->getJson('/api/v1/reading-items/'.$articleId.'/questions')->assertOk()
            ->assertDontSee('correctAnswer')->assertDontSee('explanation')->assertDontSee('"answer"');
        $this->postJson('/api/v1/reading-questions/'.$questionId.'/check', ['answer' => 0])
            ->assertOk()->assertJsonPath('correct', false)->assertJsonPath('correctAnswer', 1);
        $this->postJson('/api/v1/reading-questions/'.$questionId.'/check', ['answer' => 9])
            ->assertUnprocessable();
    }

    public function test_word_detail_requires_published_word_and_detail(): void
    {
        $wordId = DB::table('words')->insertGetId([
            'headword' => 'umbrella', 'meaning' => '雨伞', 'status' => 'published',
        ]);
        $draftId = DB::table('words')->insertGetId([
            'headword' => 'draft', 'meaning' => '草稿', 'status' => 'draft',
        ]);
        DB::table('word_details')->insert([
            'word_id' => $wordId, 'type' => 'richtext', 'html' => '<p>雨伞</p>', 'status' => 'draft',
        ]);

        $this->getJson('/api/v1/words/'.$wordId)->assertOk()
            ->assertJsonPath('headword', 'umbrella')->assertJsonPath('contentSource', null);
        $this->getJson('/api/v1/words/'.$draftId)->assertNotFound();
    }

    public function test_grammar_content_and_puzzle_answers_are_independent(): void
    {
        $lessonId = DB::table('grammar_lessons')->insertGetId([
            'title' => 'Past tense', 'status' => 'published', 'sort_order' => 1,
        ]);
        DB::table('grammar_lesson_contents')->insert([
            'grammar_lesson_id' => $lessonId, 'type' => 'richtext',
            'html' => '<p>Past tense</p>', 'status' => 'published',
        ]);
        $grammarQuestionId = DB::table('grammar_questions')->insertGetId([
            'grammar_lesson_id' => $lessonId, 'sort_order' => 1,
            'kind' => 'choice', 'prompt' => 'Choose one.',
            'options_json' => json_encode(['go', 'went']), 'answer' => '1',
        ]);
        $setId = DB::table('puzzle_sets')->insertGetId([
            'title' => 'A story', 'status' => 'published',
        ]);
        DB::table('puzzle_sentences')->insert([
            'puzzle_set_id' => $setId, 'sort_order' => 1,
            'prompt_cn' => '我去了。', 'answer_text' => 'I went.',
            'tokens_json' => json_encode([
                ['id' => 'token-2', 'text' => 'went.'],
                ['id' => 'token-1', 'text' => 'I'],
            ]),
            'answer_token_ids_json' => json_encode(['token-1', 'token-2']),
        ]);

        $this->getJson('/api/v1/grammar-lessons/'.$lessonId)->assertOk()
            ->assertJsonPath('contentSource.html', '<p>Past tense</p>');
        $this->getJson('/api/v1/grammar-lessons/'.$lessonId.'/questions')->assertOk()
            ->assertDontSee('"answer"');
        $this->postJson('/api/v1/grammar-questions/'.$grammarQuestionId.'/check', ['answer' => 1])
            ->assertOk()->assertJsonPath('correct', true);
        $this->getJson('/api/v1/puzzle-sets/'.$setId)->assertOk()
            ->assertJsonPath('sentences.0.tokens.0.id', 'token-2')
            ->assertDontSee('answerText')->assertDontSee('answer_text');
    }

    public function test_published_chapters_share_reading_detail_and_skip_drafts(): void
    {
        $seriesId = DB::table('series')->insertGetId([
            'title' => 'A Book', 'status' => 'published',
        ]);
        $chapterIds = [];
        foreach ([[1, 'published'], [2, 'draft'], [3, 'published']] as [$number, $status]) {
            $chapterIds[] = DB::table('reading_items')->insertGetId([
                'kind' => 'chapter', 'series_id' => $seriesId, 'chapter_no' => $number,
                'title' => 'Chapter '.$number, 'body_json' => json_encode([]), 'status' => $status,
            ]);
        }

        $this->getJson('/api/v1/series/'.$seriesId)->assertOk()->assertJsonCount(2, 'chapters')
            ->assertJsonPath('chapters.1.chapterNo', 3);
        $this->getJson('/api/v1/reading-items/'.$chapterIds[0])->assertOk()
            ->assertJsonPath('kind', 'chapter')->assertJsonPath('nextChapterId', $chapterIds[2]);
        $this->getJson('/api/v1/reading-items/'.$chapterIds[1])->assertNotFound();
    }

    private function reading(string $title, string $status, int $sortOrder): int
    {
        return DB::table('reading_items')->insertGetId([
            'kind' => 'article',
            'title' => $title,
            'body_json' => json_encode([['sentences' => ['Hello.'], 'translation' => '你好。']]),
            'status' => $status,
            'sort_order' => $sortOrder,
        ]);
    }
}
