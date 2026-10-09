<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LearningContentAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    public function test_only_admin_can_open_new_content_pages(): void
    {
        $this->get('/admin/grammar')->assertRedirect('/admin/login');
        $this->admin();
        $this->get('/admin/grammar')->assertOk();
        $this->get('/admin/grammar/create')->assertOk();
        $this->get('/admin/puzzles')->assertOk();
        $this->get('/admin/puzzles/create')->assertOk();
        $this->get('/admin/daily-words')->assertOk();
        $this->get('/admin/daily-words/create')->assertOk();
    }

    public function test_grammar_course_can_be_created_published_and_read_by_app(): void
    {
        $this->admin();
        $payload = [
            'title' => 'Present simple', 'summary' => '基础时态', 'level' => '基础语法',
            'sortOrder' => 3, 'contentType' => 'richtext',
            'contentHtml' => '<h3>用法</h3><p>讲述习惯。</p><script>alert(1)</script>',
            'questions' => [[
                'kind' => 'choice', 'prompt' => 'She ___ every day.',
                'options' => ['walk', 'walks', 'walking'], 'answer' => 1,
                'explanation' => '主语是第三人称单数。',
            ]],
        ];
        $this->post('/admin/grammar', $payload)->assertRedirect();
        $id = (int) DB::table('grammar_lessons')->where('title', 'Present simple')->value('id');
        $this->assertGreaterThan(0, $id);
        $this->assertStringNotContainsString('<script>', DB::table('grammar_lesson_contents')->where('grammar_lesson_id', $id)->value('html'));
        $this->getJson('/api/v1/grammar-lessons/'.$id)->assertNotFound();
        $this->post('/admin/grammar/'.$id.'/toggle')->assertRedirect();
        $this->getJson('/api/v1/grammar-lessons/'.$id)->assertOk()->assertJsonPath('contentSource.type', 'richtext');
        $questionId = DB::table('grammar_questions')->where('grammar_lesson_id', $id)->value('id');
        $this->postJson('/api/v1/grammar-questions/'.$questionId.'/check', ['answer' => 1])
            ->assertOk()->assertJsonPath('correct', true);
        $this->put('/admin/grammar/'.$id, $payload)->assertRedirect();
        $this->assertSame($questionId, DB::table('grammar_questions')->where('grammar_lesson_id', $id)->value('id'));
    }

    public function test_puzzle_editor_generates_valid_tokens_and_preserves_existing_progress(): void
    {
        $this->admin();
        $payload = [
            'title' => 'A short story', 'subtitle' => '语序练习', 'iconAsset' => '✦',
            'sortOrder' => 2,
            'sentences' => [
                ['promptCn' => '她打开了门。', 'answerText' => 'She opened the door.'],
                ['promptCn' => '雨停了。', 'answerText' => 'The rain stopped.'],
            ],
        ];
        $this->post('/admin/puzzles', $payload)->assertRedirect();
        $id = (int) DB::table('puzzle_sets')->where('title', 'A short story')->value('id');
        $this->assertGreaterThan(0, $id);
        $this->post('/admin/puzzles/'.$id.'/toggle')->assertRedirect();
        $this->getJson('/api/v1/puzzle-sets/'.$id)->assertOk()
            ->assertJsonPath('sentenceCount', 2)->assertJsonPath('sentences.0.tokens.0.text', 'She');
        $sentenceId = DB::table('puzzle_sentences')->where('puzzle_set_id', $id)->orderBy('sort_order')->value('id');
        $this->postJson('/api/v1/puzzle-sentences/'.$sentenceId.'/check', ['tokenIds' => [1, 2, 3, 4]])
            ->assertOk()->assertJsonPath('correct', true);
        $this->put('/admin/puzzles/'.$id, $payload)->assertRedirect();
        $this->assertSame($sentenceId, DB::table('puzzle_sentences')->where('puzzle_set_id', $id)->orderBy('sort_order')->value('id'));

        $learner = User::factory()->create(['is_admin' => false]);
        DB::table('puzzle_progress')->insert([
            'user_id' => $learner->id, 'puzzle_set_id' => $id, 'cursor_next' => 1, 'updated_at' => now(),
        ]);
        $this->put('/admin/puzzles/'.$id, array_merge($payload, ['sentences' => [
            ...$payload['sentences'], ['promptCn' => '结束。', 'answerText' => 'It is done.'],
        ]]))->assertSessionHasErrors('sentences');
        $this->assertSame(2, DB::table('puzzle_sentences')->where('puzzle_set_id', $id)->count());
    }

    public function test_manual_daily_plan_reaches_today_api_and_cannot_change_after_learning(): void
    {
        $this->admin();
        $wordIds = [];
        foreach (['river', 'bridge'] as $headword) {
            $wordIds[] = DB::table('words')->insertGetId([
                'headword' => $headword, 'meaning' => '测试', 'status' => 'published',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $date = now('Asia/Shanghai')->toDateString();
        $payload = ['planDate' => $date, 'wordIds' => $wordIds];
        $this->post('/admin/daily-words', $payload)->assertRedirect();
        $id = (int) DB::table('daily_word_plans')->where('plan_date', $date)->value('id');
        $this->assertGreaterThan(0, $id);
        $this->post('/admin/daily-words/'.$id.'/toggle')->assertRedirect();
        $this->getJson('/api/v1/daily-words/today')->assertOk()
            ->assertJsonPath('planId', $id)->assertJsonPath('items.0.headword', 'river')
            ->assertJsonPath('items.1.headword', 'bridge');

        $learner = User::factory()->create(['is_admin' => false]);
        DB::table('daily_word_completions')->insert([
            'user_id' => $learner->id, 'plan_id' => $id, 'word_id' => $wordIds[0], 'completed_at' => now(),
        ]);
        $this->put('/admin/daily-words/'.$id, ['planDate' => $date, 'wordIds' => array_reverse($wordIds)])
            ->assertSessionHasErrors('wordIds');
        $this->assertSame($wordIds, DB::table('daily_word_plan_items')->where('plan_id', $id)->orderBy('sort_order')->pluck('word_id')->all());
    }

    public function test_word_markdown_is_stored_as_source_and_rendered_for_app(): void
    {
        $this->admin();
        $markdown = "## 用法\n\n**river** means 河流.\n\n<script>alert(1)</script>";
        $this->post('/admin/words', [
            'headword' => 'river', 'meaning' => '河流', 'detailType' => 'richtext',
            'detailFormat' => 'markdown', 'detailHtml' => $markdown,
        ])->assertRedirect();
        $id = (int) DB::table('words')->where('headword', 'river')->value('id');
        $detail = DB::table('word_details')->where('word_id', $id)->first();
        $this->assertSame('markdown', $detail->format);
        $this->assertSame($markdown, $detail->html);
        $this->post("/admin/words/{$id}/publish")->assertRedirect();
        $html = $this->getJson("/api/v1/words/{$id}")->assertOk()->json('contentSource.html');
        $this->assertStringContainsString('<h2>用法</h2>', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_grammar_level_management_and_markdown_rendering(): void
    {
        $this->admin();
        $this->get('/admin/grammar-levels')->assertOk();
        $this->post('/admin/grammar-levels', ['name' => '高级语法'])->assertRedirect();
        $levelId = (int) DB::table('grammar_levels')->where('name', '高级语法')->value('id');
        $this->assertGreaterThan(0, $levelId);
        $this->post("/admin/grammar-levels/{$levelId}/toggle")->assertRedirect();
        $this->assertSame(0, (int) DB::table('grammar_levels')->where('id', $levelId)->value('is_active'));
        $this->post('/admin/grammar', [
            'title' => 'Disabled level', 'level' => '高级语法',
            'contentType' => 'richtext', 'contentHtml' => '<p>test</p>',
            'questions' => [['kind' => 'choice', 'prompt' => 'Choose', 'options' => ['A', 'B'], 'answer' => 0]],
        ])->assertSessionHasErrors('level');
        $this->post("/admin/grammar-levels/{$levelId}/toggle")->assertRedirect();

        $markdown = "## 一般现在时\n\nShe **walks** every day.";
        $this->post('/admin/grammar', [
            'title' => 'Present simple', 'level' => '高级语法',
            'contentType' => 'richtext', 'contentFormat' => 'markdown', 'contentHtml' => $markdown,
            'questions' => [['kind' => 'choice', 'prompt' => 'She ___.', 'options' => ['walk', 'walks'], 'answer' => 1]],
        ])->assertRedirect();
        $id = (int) DB::table('grammar_lessons')->where('title', 'Present simple')->value('id');
        $content = DB::table('grammar_lesson_contents')->where('grammar_lesson_id', $id)->first();
        $this->assertSame('markdown', $content->format);
        $this->assertSame($markdown, $content->html);
        $this->post("/admin/grammar/{$id}/toggle")->assertRedirect();
        $this->assertStringContainsString('<h2>一般现在时</h2>', $this->getJson("/api/v1/grammar-lessons/{$id}")->assertOk()->json('contentSource.html'));
    }

    public function test_daily_word_search_requires_a_query(): void
    {
        $this->admin();
        DB::table('words')->insert([
            'headword' => 'river', 'meaning' => '河流', 'status' => 'published',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->getJson('/admin/daily-words/word-options')->assertOk()->assertJsonCount(0, 'items');
        $this->getJson('/admin/daily-words/word-options?q=riv')->assertOk()->assertJsonPath('items.0.headword', 'river');
    }
}
