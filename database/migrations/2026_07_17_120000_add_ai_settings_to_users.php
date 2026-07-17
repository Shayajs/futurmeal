<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('ai_api_key')->nullable()->after('brightshield_linked_at');
            $table->string('ai_api_base_url')->nullable()->after('ai_api_key');
            $table->string('ai_api_model')->nullable()->after('ai_api_base_url');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ai_api_key', 'ai_api_base_url', 'ai_api_model']);
        });
    }
};
