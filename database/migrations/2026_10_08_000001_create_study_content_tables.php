<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('series', function (Blueprint $table): void {
            $table->increments('id');
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
            $table->increments('id');
            $table->string('slug', 100)->nullable()->unique();
            $table->string('kind', 16);
            $table->unsignedInteger('series_id')->nullable();
            $table->unsignedInteger('chapter_no')->nullable();
            $table->string('title');
            $table->string('translation')->nullable();
            $table->string('level', 32)->nullable();
            $table->unsignedSmallInteger('minutes')->nullable();
            $table->string('cover_asset')->nullable();
            $table->json('body_json');
            $table->string('status', 24)->default('draft');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('rights_type', 32)->nullable();
            $table->text('rights_note')->nullable();
            $table->timestamps();
            $table->foreign('series_id')->references('id')->on('series');
            $table->unique(['series_id', 'chapter_no']);
            $table->index(['status', 'kind', 'sort_order', 'id']);
        });

        Schema::create('reading_analyses', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('reading_item_id')->unique();
            $table->string('type', 24);
            $table->longText('html')->nullable();
            $table->text('url')->nullable();
            $table->string('title')->nullable();
            $table->text('summary')->nullable();
            $table->string('status', 24)->default('draft');
            $table->timestamps();
            $table->foreign('reading_item_id')->references('id')->on('reading_items');
        });

        Schema::create('words', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('slug', 100)->nullable()->unique();
            $table->string('headword');
            $table->string('ipa')->nullable();
            $table->string('part_of_speech', 64)->nullable();
            $table->string('meaning');
            $table->string('frequency_label')->nullable();
            $table->string('audio_asset')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->timestamps();
            $table->index('headword');
        });

        Schema::create('word_details', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('word_id')->unique();
            $table->string('type', 24);
            $table->longText('html')->nullable();
            $table->text('url')->nullable();
            $table->string('title')->nullable();
            $table->text('summary')->nullable();
            $table->string('status', 24)->default('draft');
            $table->timestamps();
            $table->foreign('word_id')->references('id')->on('words');
        });

        Schema::create('reading_words', function (Blueprint $table): void {
            $table->unsignedInteger('reading_item_id');
            $table->unsignedInteger('word_id');
            $table->unsignedInteger('sort_order');
            $table->primary(['reading_item_id', 'word_id']);
            $table->unique(['reading_item_id', 'sort_order']);
            $table->foreign('reading_item_id')->references('id')->on('reading_items');
            $table->foreign('word_id')->references('id')->on('words');
        });

        Schema::create('reading_questions', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('reading_item_id');
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
            $table->increments('id');
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('level', 32)->nullable();
            $table->string('status', 24)->default('draft');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['status', 'sort_order', 'id']);
        });

        Schema::create('grammar_lesson_contents', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('grammar_lesson_id')->unique();
            $table->string('type', 24);
            $table->longText('html')->nullable();
            $table->text('url')->nullable();
            $table->string('title')->nullable();
            $table->text('summary')->nullable();
            $table->string('status', 24)->default('draft');
            $table->timestamps();
            $table->foreign('grammar_lesson_id')->references('id')->on('grammar_lessons');
        });

        Schema::create('grammar_questions', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('grammar_lesson_id');
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
            $table->increments('id');
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('icon_asset')->nullable();
            $table->unsignedInteger('source_reading_item_id')->nullable();
            $table->string('status', 24)->default('draft');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->foreign('source_reading_item_id')->references('id')->on('reading_items');
            $table->index(['status', 'sort_order', 'id']);
        });

        Schema::create('puzzle_sentences', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('puzzle_set_id');
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
            $table->increments('id');
            $table->date('plan_date')->unique();
            $table->string('status', 24)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('daily_word_plan_items', function (Blueprint $table): void {
            $table->unsignedInteger('plan_id');
            $table->unsignedInteger('word_id');
            $table->unsignedInteger('sort_order');
            $table->primary(['plan_id', 'word_id']);
            $table->unique(['plan_id', 'sort_order']);
            $table->foreign('plan_id')->references('id')->on('daily_word_plans');
            $table->foreign('word_id')->references('id')->on('words');
        });
    }

    public function down(): void
    {
        foreach (['daily_word_plan_items', 'daily_word_plans', 'puzzle_sentences', 'puzzle_sets', 'grammar_questions', 'grammar_lesson_contents', 'grammar_lessons', 'reading_questions', 'reading_words', 'word_details', 'words', 'reading_analyses', 'reading_items', 'series'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
