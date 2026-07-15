<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->decimal('target_weight_kg', 5, 2)->nullable()->after('calorie_adjustment');
            $table->decimal('target_body_fat_percent', 5, 2)->nullable()->after('target_weight_kg');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn(['target_weight_kg', 'target_body_fat_percent']);
        });
    }
};
