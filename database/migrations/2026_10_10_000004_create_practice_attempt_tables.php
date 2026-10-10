<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practice_attempts', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('kind', 16);
            $table->unsignedInteger('content_id');
            $table->string('status', 16)->default('in_progress');
            $table->json('questions_json');
            $table->unsignedSmallInteger('total_count');
            $table->unsignedSmallInteger('correct_count')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'kind', 'content_id', 'id'], 'practice_attempt_lookup');
            $table->foreign('user_id')->references('id')->on('users');
        });

        Schema::create('practice_attempt_answers', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('attempt_id');
            $table->unsignedInteger('question_id');
            $table->json('submitted_answer_json');
            $table->boolean('is_correct');
            $table->timestamp('answered_at');
            $table->unique(['attempt_id', 'question_id']);
            $table->foreign('attempt_id')->references('id')->on('practice_attempts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_attempt_answers');
        Schema::dropIfExists('practice_attempts');
    }
};
