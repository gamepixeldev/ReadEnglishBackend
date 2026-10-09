<?php

namespace Tests\Feature;

use Database\Seeders\LegacyStudyContentSeeder;
use Database\Seeders\SampleReadingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LegacyStudyContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixture_content_is_imported_idempotently_and_available_through_apis(): void
    {
        $this->seed(SampleReadingSeeder::class);
        $this->seed(LegacyStudyContentSeeder::class);
        $wordCount = DB::table('words')->count();
        $puzzleCount = DB::table('puzzle_sets')->count();
        $this->seed(LegacyStudyContentSeeder::class);

        $this->assertSame(24, $wordCount);
        $this->assertSame(12, $puzzleCount);
        $this->assertSame($wordCount, DB::table('words')->count());
        $this->assertSame($puzzleCount, DB::table('puzzle_sets')->count());
        $this->assertSame(3, DB::table('grammar_lessons')->count());
        $this->assertSame(4, DB::table('series')->count());
        $this->assertSame(1, DB::table('reading_items')->where('slug', 'sample-blue-umbrella')->count());

        $daily = $this->getJson('/api/v1/daily-words/today')->assertOk()->json();
        $this->assertCount(4, $daily['items']);
        $this->assertSame($daily['planId'], DB::table('daily_word_plans')->value('id'));
        $this->getJson('/api/v1/grammar-lessons')->assertOk()->assertJsonCount(3, 'items');
        $this->getJson('/api/v1/puzzle-sets')->assertOk()->assertJsonCount(12, 'items');

        $setId = DB::table('puzzle_sets')->orderBy('id')->value('id');
        $sentence = $this->getJson('/api/v1/puzzle-sets/'.$setId)->assertOk()->json('sentences.0');
        $this->assertArrayNotHasKey('answer', $sentence);
        $ids = array_column($sentence['tokens'], 'id');
        $this->postJson('/api/v1/puzzle-sentences/'.$sentence['id'].'/check', ['tokenIds' => $ids])
            ->assertOk()->assertJsonPath('correct', true);
        $reversed = array_reverse($ids);
        $this->postJson('/api/v1/puzzle-sentences/'.$sentence['id'].'/check', ['tokenIds' => $reversed])
            ->assertOk()->assertJsonPath('correct', false);
    }
}
