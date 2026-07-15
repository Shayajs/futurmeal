<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_store_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('food_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label');
            $table->string('barcode')->nullable();
            $table->string('store_brand');
            $table->unsignedInteger('open_prices_location_id')->nullable();
            $table->decimal('price_per_kg', 10, 2);
            $table->date('observed_at');
            $table->timestamps();

            $table->index(['store_brand', 'food_item_id']);
            $table->index(['store_brand', 'reference_type', 'reference_id']);
            $table->index(['store_brand', 'label']);
        });

        Schema::table('budget_entries', function (Blueprint $table) {
            $table->string('store_brand')->nullable()->after('price_source');
        });

        Schema::table('food_items', function (Blueprint $table) {
            $table->boolean('is_community')->default(false)->after('raw_nutriments');
        });
    }

    public function down(): void
    {
        Schema::table('food_items', function (Blueprint $table) {
            $table->dropColumn('is_community');
        });

        Schema::table('budget_entries', function (Blueprint $table) {
            $table->dropColumn('store_brand');
        });

        Schema::dropIfExists('community_store_prices');
    }
};
