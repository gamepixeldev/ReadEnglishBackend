<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PracticeAttemptTest extends TestCase
{
    use RefreshDatabase;

    private function mobileSession(): array
    {
        $user = User::factory()->create(['is_admin' => false]);
        $token = str_repeat((string) $user->id, 64);
        DB::table('mobile_access_tokens')->insert([
            'user_id' => $user->id, 'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$user, ['Authorization' => 'Bearer '.$token]];
    }

    private function readingWithQuestions(): array
    {
        $readingId = DB::table('reading_items')->insertGetId([
            'kind' => 'article', 'title' => 'Test Story', 'body_json' => '[]',
            'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $first = DB::table('reading_questions')->insertGetId([
            'reading_item_id' => $readingId, 'sort_order' => 1, 'kind' => 'choice',
            'question_type' => '细节理解', 'prompt' => 'What happened?',
            'options_json' => json_encode(['Rain', 'Snow']), 'answer' => '1',
            'explanation' => 'It snowed.', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $second = DB::table('reading_questions')->insertGetId([
            'reading_item_id' => $readingId, 'sort_order' => 2, 'kind' => 'fill',
            'prompt' => 'Name the weather.', 'answer' => 'snow',
            'explanation' => 'The weather was snow.', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$readingId, $first, $second];
    }

    public function test_reading_attempt_resumes_saves_first_answer_and_preserves_snapshot(): void
    {
        [$user, $headers] = $this->mobileSession();
        [$readingId, $first, $second] = $this->readingWithQuestions();
        $url = '/api/v1/me/practice-attempts';
        $start = $this->postJson($url, ['kind' => 'reading', 'contentId' => $readingId], $headers)
            ->assertOk()->assertJsonMissingPath('attempt.questions.0.correctAnswer');
        $attemptId = $start->json('attempt.id');
        $this->postJson($url.'/'.$attemptId.'/answers', ['questionId' => $first, 'answer' => 0], $headers)
            ->assertOk()->assertJsonPath('attempt.questions.0.correct', false)
            ->assertJsonPath('attempt.questions.0.correctAnswer', 1)
            ->assertJsonMissingPath('attempt.questions.1.correctAnswer');
        $this->getJson($url.'?kind=reading&contentId='.$readingId, $headers)
            ->assertJsonPath('active.answeredCount', 1)
            ->assertJsonPath('active.questions.1.id', $second);
        $this->postJson($url.'/'.$attemptId.'/answers', ['questionId' => $first, 'answer' => 1], $headers)
            ->assertJsonPath('attempt.questions.0.submittedAnswer', 0);
        $this->postJson($url.'/'.$attemptId.'/answers', ['questionId' => $second, 'answer' => '  SNOW '], $headers)
            ->assertJsonPath('attempt.status', 'completed')->assertJsonPath('attempt.correctCount', 1);
        $this->assertSame(0, DB::table('reading_completions')->where('user_id', $user->id)->count());

        DB::table('reading_questions')->where('id', $first)->update(['prompt' => 'Changed question', 'answer' => '0']);
        $this->getJson($url.'/'.$attemptId, $headers)
            ->assertJsonPath('attempt.questions.0.prompt', 'What happened?')
            ->assertJsonPath('attempt.questions.0.correctAnswer', 1);
        $this->getJson($url.'?kind=reading&contentId='.$readingId, $headers)
            ->assertJsonPath('latest.correctCount', 1)->assertJsonCount(1, 'history');
        $this->assertSame(2, DB::table('practice_attempt_answers')->where('attempt_id', $attemptId)->count());

        [, $otherHeaders] = $this->mobileSession();
        $this->getJson($url.'/'.$attemptId, $otherHeaders)->assertNotFound();
    }

    public function test_grammar_retry_creates_new_round_and_completion_updates_recent(): void
    {
        [$user, $headers] = $this->mobileSession();
        $lessonId = DB::table('grammar_lessons')->insertGetId([
            'title' => 'Tenses', 'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $questionId = DB::table('grammar_questions')->insertGetId([
            'grammar_lesson_id' => $lessonId, 'sort_order' => 1, 'kind' => 'choice',
            'prompt' => 'Choose the tense.', 'options_json' => json_encode(['Past', 'Present']),
            'answer' => '0', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $url = '/api/v1/me/practice-attempts';
        $firstId = $this->postJson($url, ['kind' => 'grammar', 'contentId' => $lessonId], $headers)->json('attempt.id');
        $this->postJson($url, ['kind' => 'grammar', 'contentId' => $lessonId], $headers)
            ->assertJsonPath('attempt.id', $firstId);
        $this->postJson($url.'/'.$firstId.'/answers', ['questionId' => $questionId, 'answer' => 0], $headers)
            ->assertJsonPath('attempt.status', 'completed');
        $this->assertDatabaseHas('recent_activity', ['user_id' => $user->id, 'category' => 'grammar', 'target_id' => $lessonId]);
        $secondId = $this->postJson($url, ['kind' => 'grammar', 'contentId' => $lessonId, 'restart' => true], $headers)
            ->json('attempt.id');
        $this->assertNotSame($firstId, $secondId);
        $this->getJson($url.'?kind=grammar&contentId='.$lessonId, $headers)
            ->assertJsonPath('active.id', $secondId)->assertJsonPath('latest.id', $firstId);
        $this->postJson($url.'/'.$secondId.'/answers', ['questionId' => $questionId, 'answer' => 9], $headers)
            ->assertUnprocessable();
    }

    public function test_guest_cannot_create_or_read_attempts(): void
    {
        $this->getJson('/api/v1/me/practice-attempts?kind=reading&contentId=1')->assertUnauthorized();
        $this->postJson('/api/v1/me/practice-attempts', ['kind' => 'reading', 'contentId' => 1])
            ->assertUnauthorized();
    }
}
