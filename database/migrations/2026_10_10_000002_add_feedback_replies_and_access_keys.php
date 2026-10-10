<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_feedback', function (Blueprint $table): void {
            $table->string('access_token_hash', 64)->nullable()->unique();
            $table->text('admin_reply')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('seen_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('content_feedback', function (Blueprint $table): void {
            $table->dropUnique(['access_token_hash']);
            $table->dropColumn(['access_token_hash', 'admin_reply', 'responded_at', 'seen_at']);
        });
    }
};
