<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_sources', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->string('type', 32);
            $table->longText('html')->nullable();
            $table->text('url')->nullable();
            $table->string('title')->nullable();
            $table->text('summary')->nullable();
            $table->timestamps();
        });

        Schema::create('series', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->string('title');
            $table->string('translation')->nullable();
            $table->string('author')->nullable();
            $table->string('level', 32)->nullable();
            $table->text('description')->nullable();
            $table->string('cover_asset')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('rights_type', 32)->nullable();
            $table->text('rights_note')->nullable();
            $table->timestamps();
        });

        Schema::create('reading_items', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->string('kind', 16);
            $table->string('series_id', 64)->nullable();
            $table->unsignedInteger('chapter_no')->nullable();
            $table->string('title');
            $table->string('translation')->nullable();
            $table->string('level', 32)->nullable();
            $table->unsignedSmallInteger('minutes')->nullable();
            $table->string('cover_asset')->nullable();
            $table->json('body_json');
            $table->string('grammar_source_id', 64)->nullable();
            $table->string('status', 24)->default('draft');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('rights_type', 32)->nullable();
            $table->text('rights_note')->nullable();
            $table->timestamps();
            $table->foreign('series_id')->references('id')->on('series');
            $table->foreign('grammar_source_id')->references('id')->on('content_sources');
            $table->unique(['series_id', 'chapter_no']);
            $table->index(['status', 'kind', 'sort_order', 'id']);
        });

        Schema::create('words', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->string('headword');
            $table->string('ipa')->nullable();
            $table->string('part_of_speech', 64)->nullable();
            $table->string('meaning');
            $table->string('frequency_label')->nullable();
            $table->string('audio_asset')->nullable();
            $table->string('content_source_id', 64)->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->timestamps();
            $table->foreign('content_source_id')->references('id')->on('content_sources');
            $table->index('headword');
        });

        Schema::create('reading_words', function (Blueprint $table): void {
            $table->string('reading_item_id', 64);
            $table->string('word_id', 64);
            $table->unsignedInteger('sort_order');
            $table->primary(['reading_item_id', 'word_id']);
            $table->unique(['reading_item_id', 'sort_order']);
            $table->foreign('reading_item_id')->references('id')->on('reading_items');
            $table->foreign('word_id')->references('id')->on('words');
        });

        Schema::create('reading_questions', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->string('reading_item_id', 64);
            $table->unsignedInteger('sort_order');
            $table->string('question_type', 32)->nullable();
            $table->string('kind', 16);
            $table->text('prompt');
            $table->text('prompt_cn')->nullable();
            $table->json('options_json')->nullable();
            $table->text('answer');
            $table->text('explanation')->nullable();
            $table->timestamps();
            $table->foreign('reading_item_id')->references('id')->on('reading_items');
            $table->unique(['reading_item_id', 'sort_order']);
        });

        Schema::create('grammar_lessons', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('level', 32)->nullable();
            $table->string('content_source_id', 64);
            $table->string('status', 24)->default('draft');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->foreign('content_source_id')->references('id')->on('content_sources');
            $table->index(['status', 'sort_order', 'id']);
        });

        Schema::create('grammar_questions', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->string('grammar_lesson_id', 64);
            $table->unsignedInteger('sort_order');
            $table->string('kind', 16);
            $table->text('prompt');
            $table->json('options_json')->nullable();
            $table->text('answer');
            $table->text('explanation')->nullable();
            $table->timestamps();
            $table->foreign('grammar_lesson_id')->references('id')->on('grammar_lessons');
            $table->unique(['grammar_lesson_id', 'sort_order']);
        });

        Schema::create('puzzle_sets', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('icon_asset')->nullable();
            $table->string('source_reading_item_id', 64)->nullable();
            $table->string('status', 24)->default('draft');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->foreign('source_reading_item_id')->references('id')->on('reading_items');
            $table->index(['status', 'sort_order', 'id']);
        });

        Schema::create('puzzle_sentences', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->string('puzzle_set_id', 64);
            $table->unsignedInteger('sort_order');
            $table->text('prompt_cn');
            $table->text('answer_text');
            $table->json('tokens_json');
            $table->json('answer_token_ids_json');
            $table->timestamps();
            $table->foreign('puzzle_set_id')->references('id')->on('puzzle_sets');
            $table->unique(['puzzle_set_id', 'sort_order']);
        });

        Schema::create('daily_word_plans', function (Blueprint $table): void {
            $table->date('plan_date')->primary();
            $table->string('status', 24)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('daily_word_plan_items', function (Blueprint $table): void {
            $table->date('plan_date');
            $table->string('word_id', 64);
            $table->unsignedInteger('sort_order');
            $table->primary(['plan_date', 'word_id']);
            $table->unique(['plan_date', 'sort_order']);
            $table->foreign('plan_date')->references('plan_date')->on('daily_word_plans');
            $table->foreign('word_id')->references('id')->on('words');
        });
    }

    public function down(): void
    {
        foreach (['daily_word_plan_items', 'daily_word_plans', 'puzzle_sentences', 'puzzle_sets', 'grammar_questions', 'grammar_lessons', 'reading_questions', 'reading_words', 'words', 'reading_items', 'series', 'content_sources'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
