<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('viewer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('initiated_by')->constrained('users')->cascadeOnDelete();
            $table->string('direction');
            $table->string('status')->default('pending');
            $table->boolean('can_edit')->default(false);
            $table->timestamps();

            $table->unique(['owner_id', 'viewer_id']);
        });

        Schema::table('user_profiles', function (Blueprint $table) {
            $table->foreignId('plan_view_user_id')->nullable()->after('target_body_fat_percent')->constrained('users')->nullOnDelete();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_view_user_id');
        });
        Schema::dropIfExists('plan_shares');
    }
};
