<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopping_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('range_start');
            $table->date('range_end');
            $table->timestamps();

            $table->unique(['user_id', 'range_start', 'range_end']);
        });

        Schema::create('shopping_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shopping_list_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20); // aggregated | custom
            $table->string('aggregate_key')->nullable();
            $table->string('label');
            $table->float('quantity_g')->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('food_item_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_checked')->default(false);
            $table->timestamp('checked_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['shopping_list_id', 'source']);
            $table->index(['shopping_list_id', 'aggregate_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopping_list_items');
        Schema::dropIfExists('shopping_lists');
    }
};
