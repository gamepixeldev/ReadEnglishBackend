<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('word_senses', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('word_id');
            $table->string('part_of_speech', 64)->nullable();
            $table->string('meaning');
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestamps();
            $table->index(['word_id', 'sort_order']);
            $table->foreign('word_id')->references('id')->on('words')->cascadeOnDelete();
        });

        $now = now();
        DB::table('words')->orderBy('id')->select('id', 'part_of_speech', 'meaning')->chunkById(200, function ($words) use ($now): void {
            foreach ($words as $word) {
                DB::table('word_senses')->insert([
                    'word_id' => $word->id,
                    'part_of_speech' => $word->part_of_speech,
                    'meaning' => $word->meaning,
                    'sort_order' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('word_senses');
    }
};
