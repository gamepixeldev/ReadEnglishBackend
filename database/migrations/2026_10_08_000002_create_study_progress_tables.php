<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_identities', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('provider', 32);
            $table->string('provider_subject');
            $table->timestamps();
            $table->unique(['provider', 'provider_subject']);
            $table->foreign('user_id')->references('id')->on('users');
        });

        Schema::create('reading_completions', function (Blueprint $table): void {
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('reading_item_id');
            $table->timestamp('completed_at');
            $table->primary(['user_id', 'reading_item_id']);
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('reading_item_id')->references('id')->on('reading_items');
        });

        Schema::create('word_learning_events', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('word_id');
            $table->string('source_kind', 16);
            $table->unsignedInteger('reading_item_id')->nullable();
            $table->date('plan_date')->nullable();
            $table->timestamp('completed_at');
            $table->string('request_id', 64);
            $table->unique(['user_id', 'request_id']);
            $table->index(['user_id', 'completed_at']);
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('word_id')->references('id')->on('words');
            $table->foreign('reading_item_id')->references('id')->on('reading_items');
        });

        Schema::create('daily_word_completions', function (Blueprint $table): void {
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('plan_id');
            $table->unsignedInteger('word_id');
            $table->timestamp('completed_at');
            $table->primary(['user_id', 'plan_id', 'word_id']);
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign(['plan_id', 'word_id'])->references(['plan_id', 'word_id'])->on('daily_word_plan_items');
        });

        Schema::create('word_favorites', function (Blueprint $table): void {
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('word_id');
            $table->timestamp('created_at');
            $table->primary(['user_id', 'word_id']);
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('word_id')->references('id')->on('words');
        });

        Schema::create('puzzle_progress', function (Blueprint $table): void {
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('puzzle_set_id');
            $table->unsignedInteger('cursor_next')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('updated_at');
            $table->primary(['user_id', 'puzzle_set_id']);
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('puzzle_set_id')->references('id')->on('puzzle_sets');
        });

        Schema::create('recent_activity', function (Blueprint $table): void {
            $table->unsignedInteger('user_id');
            $table->string('category', 16);
            $table->unsignedInteger('target_id');
            $table->timestamp('last_active_at');
            $table->primary(['user_id', 'category', 'target_id']);
            $table->index(['user_id', 'category', 'last_active_at']);
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        foreach (['recent_activity', 'puzzle_progress', 'word_favorites', 'daily_word_completions', 'word_learning_events', 'reading_completions', 'user_identities'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
