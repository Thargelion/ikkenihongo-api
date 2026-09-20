<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table) {
            $table->foreignUuid('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('daily_goal_minutes')->default(20);
            $table->boolean('reminders_enabled')->default(false);
            $table->boolean('sound_enabled')->default(true);
            $table->string('preferred_script')->default('ROMAJI');
            $table->timestamps();
        });

        Schema::create('user_stats', function (Blueprint $table) {
            $table->foreignUuid('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('level')->default(1);
            $table->unsignedInteger('xp_total')->default(0);
            $table->unsignedInteger('xp_into_level')->default(0);
            $table->unsignedInteger('xp_for_next_level')->default(1);
            $table->unsignedInteger('streak_days')->default(0);
            $table->date('last_study_date')->nullable();
            $table->timestamps();
        });

        Schema::create('study_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('source_key')->unique();
            $table->string('type', 16);
            $table->string('jlpt_level', 2)->nullable();
            $table->string('script', 10)->nullable();
            $table->string('glyph')->nullable();
            $table->json('romaji')->nullable();
            $table->string('kana_row')->nullable();
            $table->string('kana_column')->nullable();
            $table->string('surface')->nullable();
            $table->string('reading')->nullable();
            $table->json('meanings_es')->nullable();
            $table->json('onyomi')->nullable();
            $table->json('kunyomi')->nullable();
            $table->unsignedTinyInteger('stroke_count')->nullable();
            $table->timestamps();

            $table->index(['type', 'jlpt_level']);
            $table->index(['type', 'script']);
        });

        Schema::create('user_item_progress', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('study_item_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('NEW');
            $table->unsignedInteger('times_seen')->default(0);
            $table->unsignedInteger('times_correct')->default(0);
            $table->timestamp('next_review_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'study_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_item_progress');
        Schema::dropIfExists('study_items');
        Schema::dropIfExists('user_stats');
        Schema::dropIfExists('user_settings');
    }
};
