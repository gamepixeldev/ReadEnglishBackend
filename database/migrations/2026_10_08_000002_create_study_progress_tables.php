<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_identities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('provider', 32);
            $table->string('provider_subject');
            $table->timestamps();
            $table->unique(['provider', 'provider_subject']);
        });

        Schema::create('reading_completions', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained();
            $table->string('reading_item_id', 64);
            $table->timestamp('completed_at');
            $table->primary(['user_id', 'reading_item_id']);
            $table->foreign('reading_item_id')->references('id')->on('reading_items');
        });

        Schema::create('word_learning_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('word_id', 64);
            $table->string('source_kind', 16);
            $table->string('reading_item_id', 64)->nullable();
            $table->date('plan_date')->nullable();
            $table->timestamp('completed_at');
            $table->string('request_id', 64);
            $table->unique(['user_id', 'request_id']);
            $table->index(['user_id', 'completed_at']);
            $table->foreign('word_id')->references('id')->on('words');
            $table->foreign('reading_item_id')->references('id')->on('reading_items');
        });

        Schema::create('daily_word_completions', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained();
            $table->date('plan_date');
            $table->string('word_id', 64);
            $table->timestamp('completed_at');
            $table->primary(['user_id', 'plan_date', 'word_id']);
            $table->foreign(['plan_date', 'word_id'])->references(['plan_date', 'word_id'])->on('daily_word_plan_items');
        });

        Schema::create('word_favorites', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained();
            $table->string('word_id', 64);
            $table->timestamp('created_at');
            $table->primary(['user_id', 'word_id']);
            $table->foreign('word_id')->references('id')->on('words');
        });

        Schema::create('puzzle_progress', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained();
            $table->string('puzzle_set_id', 64);
            $table->unsignedInteger('cursor_next')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('updated_at');
            $table->primary(['user_id', 'puzzle_set_id']);
            $table->foreign('puzzle_set_id')->references('id')->on('puzzle_sets');
        });

        Schema::create('recent_activity', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained();
            $table->string('category', 16);
            $table->string('target_id', 64);
            $table->timestamp('last_active_at');
            $table->primary(['user_id', 'category', 'target_id']);
            $table->index(['user_id', 'category', 'last_active_at']);
        });
    }

    public function down(): void
    {
        foreach (['recent_activity', 'puzzle_progress', 'word_favorites', 'daily_word_completions', 'word_learning_events', 'reading_completions', 'user_identities'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
