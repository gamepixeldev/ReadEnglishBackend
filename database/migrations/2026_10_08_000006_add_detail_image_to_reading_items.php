<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reading_items', function (Blueprint $table): void {
            $table->string('detail_image_asset')->nullable()->after('cover_asset');
        });
    }

    public function down(): void
    {
        Schema::table('reading_items', function (Blueprint $table): void {
            $table->dropColumn('detail_image_asset');
        });
    }
};
