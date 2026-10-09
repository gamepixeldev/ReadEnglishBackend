<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudyProgressTest extends TestCase
{
    use RefreshDatabase;

    private function mobileSession(): array
    {
        $user = User::factory()->create(['is_admin' => false]);
        $token = str_repeat((string) $user->id, 64);
        DB::table('mobile_access_tokens')->insert([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDay(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$user, ['Authorization' => 'Bearer '.$token]];
    }

    public function test_guest_cannot_read_or_write_progress(): void
    {
        $this->getJson('/api/v1/me/study-progress')->assertUnauthorized();
        $this->postJson('/api/v1/me/reading-completions', ['readingItemId' => 1])->assertUnauthorized();
    }

    public function test_reading_completion_is_idempotent_and_isolated_by_user(): void
    {
        $id = DB::table('reading_items')->insertGetId([
            'kind' => 'article', 'title' => 'Test', 'body_json' => '[]', 'status' => 'published',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        [$user, $headers] = $this->mobileSession();
        $this->postJson('/api/v1/me/reading-completions', ['readingItemId' => $id], $headers)->assertOk();
        $this->postJson('/api/v1/me/reading-completions', ['readingItemId' => $id], $headers)->assertOk();
        $this->assertSame(1, DB::table('reading_completions')->where('user_id', $user->id)->count());
        $this->getJson('/api/v1/me/study-progress', $headers)->assertOk()
            ->assertJsonPath('readingIds.0', $id)->assertJsonPath('recent.0.category', 'reading');
        $this->deleteJson('/api/v1/me/recent-activity', [], $headers)->assertOk();
        $this->getJson('/api/v1/me/study-progress', $headers)->assertJsonCount(0, 'recent')
            ->assertJsonPath('readingIds.0', $id);
        [, $otherHeaders] = $this->mobileSession();
        $this->getJson('/api/v1/me/study-progress', $otherHeaders)->assertOk()->assertJsonCount(0, 'readingIds');
    }

    public function test_word_completion_requires_real_published_source_and_request_id_is_idempotent(): void
    {
        [$user, $headers] = $this->mobileSession();
        $wordId = DB::table('words')->insertGetId([
            'headword' => 'light', 'meaning' => '光', 'status' => 'published',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $readingId = DB::table('reading_items')->insertGetId([
            'kind' => 'article', 'title' => 'Test', 'body_json' => '[]', 'status' => 'published',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $payload = ['wordId' => $wordId, 'sourceKind' => 'reading', 'readingItemId' => $readingId, 'requestId' => 'attempt-1'];
        $this->postJson('/api/v1/me/word-learning-events', $payload, $headers)->assertNotFound();
        DB::table('reading_words')->insert(['reading_item_id' => $readingId, 'word_id' => $wordId, 'sort_order' => 1]);
        $this->postJson('/api/v1/me/word-learning-events', $payload, $headers)->assertOk();
        $this->postJson('/api/v1/me/word-learning-events', $payload, $headers)->assertOk();
        $this->assertSame(1, DB::table('word_learning_events')->where('user_id', $user->id)->count());
        $this->putJson('/api/v1/me/word-favorites/'.$wordId, [], $headers)->assertOk();
        $this->getJson('/api/v1/me/study-progress', $headers)->assertJsonPath('wordIds.0', $wordId)
            ->assertJsonPath('favoriteWordIds.0', $wordId);
        $this->deleteJson('/api/v1/me/word-favorites/'.$wordId, [], $headers)->assertOk();
        $this->getJson('/api/v1/me/study-progress', $headers)->assertJsonCount(0, 'favoriteWordIds');
    }

    public function test_puzzle_progress_advances_and_only_explicit_reset_restarts(): void
    {
        [, $headers] = $this->mobileSession();
        $setId = DB::table('puzzle_sets')->insertGetId([
            'title' => 'Test', 'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([1, 2, 3] as $number) {
            DB::table('puzzle_sentences')->insert([
                'puzzle_set_id' => $setId, 'sort_order' => $number,
                'prompt_cn' => '测试', 'answer_text' => 'A test.',
                'tokens_json' => '[]', 'answer_token_ids_json' => '[]',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $url = '/api/v1/me/puzzle-progress/'.$setId;
        $this->putJson($url, ['cursorNext' => 2], $headers)->assertJsonPath('cursorNext', 2);
        $this->putJson($url, ['cursorNext' => 1], $headers)->assertJsonPath('cursorNext', 2);
        $this->putJson($url, ['cursorNext' => 4], $headers)->assertUnprocessable();
        $this->putJson($url, ['cursorNext' => 0, 'reset' => true], $headers)->assertJsonPath('cursorNext', 0);
    }

    public function test_daily_word_and_grammar_activity_appear_in_account_progress(): void
    {
        [, $headers] = $this->mobileSession();
        $wordId = DB::table('words')->insertGetId([
            'headword' => 'day', 'meaning' => '一天', 'status' => 'published',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $date = now('Asia/Shanghai')->toDateString();
        $planId = DB::table('daily_word_plans')->insertGetId([
            'plan_date' => $date, 'status' => 'published',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('daily_word_plan_items')->insert(['plan_id' => $planId, 'word_id' => $wordId, 'sort_order' => 1]);
        $this->postJson('/api/v1/me/word-learning-events', [
            'wordId' => $wordId, 'sourceKind' => 'daily', 'planDate' => $date, 'requestId' => 'daily-1',
        ], $headers)->assertOk();
        $grammarId = DB::table('grammar_lessons')->insertGetId([
            'title' => 'Past tense', 'status' => 'published',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->postJson('/api/v1/me/grammar-activity/'.$grammarId, [], $headers)->assertOk();
        $this->getJson('/api/v1/me/study-progress', $headers)->assertJsonPath('dailyWordIdsByDate.'.$date.'.0', $wordId)
            ->assertJsonFragment(['category' => 'grammar']);
    }
}
