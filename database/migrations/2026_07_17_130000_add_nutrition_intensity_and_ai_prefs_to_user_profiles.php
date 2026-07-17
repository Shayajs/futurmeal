<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->unsignedInteger('sport_kcal_per_day')->default(0)->after('calorie_adjustment');
            $table->string('goal_intensity')->nullable()->after('sport_kcal_per_day');
            $table->string('ai_whey')->default('none')->after('goal_intensity');
            $table->string('ai_meal_complexity')->default('simple_budget')->after('ai_whey');
            $table->json('ai_forbidden_foods')->nullable()->after('ai_meal_complexity');
            $table->json('ai_preferred_foods')->nullable()->after('ai_forbidden_foods');
            $table->unsignedTinyInteger('ai_tasty_days_per_week')->default(1)->after('ai_preferred_foods');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'sport_kcal_per_day',
                'goal_intensity',
                'ai_whey',
                'ai_meal_complexity',
                'ai_forbidden_foods',
                'ai_preferred_foods',
                'ai_tasty_days_per_week',
            ]);
        });
    }
};
