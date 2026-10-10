<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_feedback', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('user_id')->nullable()->index();
            $table->string('category', 24);
            $table->string('target_type', 24)->default('general');
            $table->unsignedInteger('target_id')->nullable();
            $table->string('target_title')->nullable();
            $table->text('message');
            $table->string('contact', 255)->nullable();
            $table->string('status', 24)->default('pending');
            $table->text('admin_note')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_feedback');
    }
};
