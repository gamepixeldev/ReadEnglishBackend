<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reading_levels', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 32)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        $levels = collect(config('reading.levels', []))
            ->map(fn (array $level): string => trim((string) ($level['value'] ?? '')))
            ->filter()
            ->unique()
            ->values();

        foreach (['series', 'reading_items'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $levels = $levels->merge(DB::table($table)->whereNotNull('level')->distinct()->pluck('level'))
                ->map(fn ($level): string => trim((string) $level))
                ->filter()
                ->unique()
                ->values();
        }

        DB::table('reading_levels')->insert($levels->map(fn (string $name, int $index): array => [
            'name' => $name,
            'is_active' => true,
            'sort_order' => $index + 1,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_levels');
    }
};
