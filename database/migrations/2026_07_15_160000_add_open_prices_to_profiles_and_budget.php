<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->unsignedInteger('open_prices_location_id')->nullable()->after('plan_view_user_id');
            $table->string('open_prices_location_label')->nullable()->after('open_prices_location_id');
        });

        Schema::table('budget_entries', function (Blueprint $table) {
            $table->string('price_source')->default('user')->after('price_per_kg');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn(['open_prices_location_id', 'open_prices_location_label']);
        });

        Schema::table('budget_entries', function (Blueprint $table) {
            $table->dropColumn('price_source');
        });
    }
};
