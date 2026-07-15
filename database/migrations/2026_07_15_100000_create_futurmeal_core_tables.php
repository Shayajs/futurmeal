<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('onboarding_completed_at')->nullable()->after('remember_token');
        });

        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('gender')->default('other');
            $table->date('birth_date')->nullable();
            $table->decimal('height_cm', 5, 2)->nullable();
            $table->string('activity_level')->default('moderate');
            $table->string('goal_type')->default('weight_loss');
            $table->unsignedSmallInteger('planning_horizon_days')->default(7);
            $table->integer('daily_calorie_target')->nullable();
            $table->integer('calorie_adjustment')->default(-400);
            $table->timestamps();
        });

        Schema::create('body_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('recorded_at');
            $table->decimal('weight_kg', 5, 2);
            $table->decimal('body_fat_percent', 5, 2)->nullable();
            $table->decimal('lean_mass_kg', 5, 2)->nullable();
            $table->decimal('bmi', 5, 2)->nullable();
            $table->string('source')->default('manual');
            $table->decimal('neck_cm', 5, 2)->nullable();
            $table->decimal('waist_cm', 5, 2)->nullable();
            $table->decimal('hip_cm', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'recorded_at']);
        });

        Schema::create('ciqual_foods', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('alim_code')->unique();
            $table->string('name_fr');
            $table->string('name_en')->nullable();
            $table->string('group_name')->nullable();
            $table->timestamps();
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->fullText(['name_fr', 'name_en']);
            }
        });

        Schema::create('ciqual_nutrients', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name_fr');
            $table->string('unit');
        });

        Schema::create('ciqual_composition', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ciqual_food_id')->constrained('ciqual_foods')->cascadeOnDelete();
            $table->foreignId('ciqual_nutrient_id')->constrained('ciqual_nutrients')->cascadeOnDelete();
            $table->decimal('value_per_100g', 12, 4)->nullable();
            $table->unique(['ciqual_food_id', 'ciqual_nutrient_id'], 'ciqual_composition_unique');
        });

        Schema::create('food_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference_type');
            $table->string('external_id')->nullable();
            $table->string('name');
            $table->string('brand')->nullable();
            $table->decimal('energy_kcal', 10, 2)->default(0);
            $table->decimal('protein_g', 10, 2)->default(0);
            $table->decimal('carbs_g', 10, 2)->default(0);
            $table->decimal('fat_g', 10, 2)->default(0);
            $table->decimal('fiber_g', 10, 2)->default(0);
            $table->decimal('salt_g', 10, 2)->default(0);
            $table->json('raw_nutriments')->nullable();
            $table->timestamps();

            $table->index(['reference_type', 'external_id']);
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->fullText('name');
            }
        });

        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('instructions')->nullable();
            $table->unsignedSmallInteger('servings')->default(1);
            $table->boolean('is_macro_preset')->default(false);
            $table->decimal('preset_energy_kcal', 10, 2)->nullable();
            $table->decimal('preset_protein_g', 10, 2)->nullable();
            $table->decimal('preset_carbs_g', 10, 2)->nullable();
            $table->decimal('preset_fat_g', 10, 2)->nullable();
            $table->decimal('preset_portion_g', 10, 2)->nullable();
            $table->string('themealdb_id')->nullable();
            $table->timestamps();
        });

        Schema::create('recipe_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->string('reference_type');
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('food_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label');
            $table->decimal('quantity_g', 10, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
        });

        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('invite_code', 12)->unique();
            $table->boolean('lock_portions')->default(false);
            $table->date('week_starts_on')->nullable();
            $table->timestamps();
        });

        Schema::create('meal_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();
        });

        Schema::create('meal_plan_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_plan_id')->constrained()->cascadeOnDelete();
            $table->date('planned_on');
            $table->string('meal_slot')->default('lunch');
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->decimal('portions', 5, 2)->default(1);
            $table->decimal('estimated_cost', 10, 2)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
        });

        Schema::create('program_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member');
            $table->boolean('share_metrics')->default(false);
            $table->timestamps();

            $table->unique(['program_id', 'user_id']);
        });

        Schema::create('program_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->string('email')->nullable();
            $table->string('token', 64)->unique();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('budget_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reference_type');
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('food_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label');
            $table->decimal('price_per_kg', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_entries');
        Schema::dropIfExists('program_invitations');
        Schema::dropIfExists('program_members');
        Schema::dropIfExists('programs');
        Schema::dropIfExists('meal_plan_entries');
        Schema::dropIfExists('meal_plans');
        Schema::dropIfExists('recipe_ingredients');
        Schema::dropIfExists('recipes');
        Schema::dropIfExists('food_items');
        Schema::dropIfExists('ciqual_composition');
        Schema::dropIfExists('ciqual_nutrients');
        Schema::dropIfExists('ciqual_foods');
        Schema::dropIfExists('body_metrics');
        Schema::dropIfExists('user_profiles');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('onboarding_completed_at');
        });
    }
};
