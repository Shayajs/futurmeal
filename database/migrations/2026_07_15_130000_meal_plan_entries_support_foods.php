<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meal_plan_entries', function (Blueprint $table) {
            $table->dropForeign(['recipe_id']);
        });

        Schema::table('meal_plan_entries', function (Blueprint $table) {
            $table->foreignId('recipe_id')->nullable()->change();
            $table->string('reference_type')->nullable()->after('recipe_id');
            $table->unsignedBigInteger('reference_id')->nullable()->after('reference_type');
            $table->foreignId('food_item_id')->nullable()->after('reference_id')->constrained()->nullOnDelete();
            $table->string('label')->nullable()->after('food_item_id');
            $table->decimal('quantity_g', 10, 2)->nullable()->after('label');
        });

        Schema::table('meal_plan_entries', function (Blueprint $table) {
            $table->foreign('recipe_id')->references('id')->on('recipes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('meal_plan_entries', function (Blueprint $table) {
            $table->dropForeign(['recipe_id']);
            $table->dropForeign(['food_item_id']);
            $table->dropColumn(['reference_type', 'reference_id', 'food_item_id', 'label', 'quantity_g']);
        });

        Schema::table('meal_plan_entries', function (Blueprint $table) {
            $table->foreignId('recipe_id')->nullable(false)->change();
            $table->foreign('recipe_id')->references('id')->on('recipes')->cascadeOnDelete();
        });
    }
};
