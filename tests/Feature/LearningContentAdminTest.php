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
}
