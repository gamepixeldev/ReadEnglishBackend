<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_feedback_replies', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('feedback_id');
            $table->string('kind', 24)->default('update');
            $table->text('message');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('feedback_id')->references('id')->on('content_feedback')->onDelete('cascade');
            $table->index(['feedback_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_feedback_replies');
    }
};
