<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('name')->nullable()->change();
            $table->string('avatar_url', 2048)->nullable()->after('name');
        });

        Schema::table('user_identities', function (Blueprint $table): void {
            $table->string('provider_app_id', 128)->after('provider');
            $table->dropUnique(['provider', 'provider_subject']);
            $table->unique(
                ['provider', 'provider_app_id', 'provider_subject'],
                'user_identities_provider_app_subject_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('user_identities', function (Blueprint $table): void {
            $table->dropUnique('user_identities_provider_app_subject_unique');
            $table->dropColumn('provider_app_id');
            $table->unique(['provider', 'provider_subject']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('avatar_url');
            $table->string('name')->nullable(false)->change();
        });
    }
};
