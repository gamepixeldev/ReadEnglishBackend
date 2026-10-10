<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grammar_lessons', function (Blueprint $table): void {
            $table->string('cover_asset')->nullable()->after('level');
        });
        Schema::table('puzzle_sets', function (Blueprint $table): void {
            $table->string('cover_asset')->nullable()->after('subtitle');
        });
    }

    public function down(): void
    {
        Schema::table('puzzle_sets', function (Blueprint $table): void {
            $table->dropColumn('cover_asset');
        });
        Schema::table('grammar_lessons', function (Blueprint $table): void {
            $table->dropColumn('cover_asset');
        });
    }
};
