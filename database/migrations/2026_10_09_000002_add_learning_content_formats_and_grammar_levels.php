<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('word_details', function (Blueprint $table): void {
            $table->string('format', 16)->default('html')->after('type');
        });
        Schema::table('grammar_lesson_contents', function (Blueprint $table): void {
            $table->string('format', 16)->default('html')->after('type');
        });
        Schema::create('grammar_levels', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 32)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $levels = DB::table('grammar_lessons')->whereNotNull('level')->distinct()->pluck('level')
            ->map(fn ($name) => trim((string) $name))->filter()->unique()->values();
        if ($levels->isEmpty()) $levels = collect(['基础语法', '进阶语法']);
        $now = now();
        DB::table('grammar_levels')->insert($levels->map(fn (string $name, int $index) => [
            'name' => $name, 'is_active' => true, 'sort_order' => $index + 1,
            'created_at' => $now, 'updated_at' => $now,
        ])->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('grammar_levels');
        Schema::table('grammar_lesson_contents', function (Blueprint $table): void {
            $table->dropColumn('format');
        });
        Schema::table('word_details', function (Blueprint $table): void {
            $table->dropColumn('format');
        });
    }
};
