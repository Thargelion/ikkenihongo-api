<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('exercise');
            $table->string('format');
            $table->unsignedTinyInteger('length')->default(10);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedSmallInteger('correct_count')->default(0);
            $table->unsignedInteger('xp_earned')->default(0);
            $table->timestamps();
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('study_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->foreignUuid('study_item_id')->constrained()->cascadeOnDelete();
            $table->string('prompt');
            $table->string('direction');
            $table->json('accepted_scripts');
            $table->timestamps();

            $table->unique(['study_session_id', 'position']);
        });

        Schema::create('answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('question_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('raw_input');
            $table->string('detected_script');
            $table->string('normalized');
            $table->boolean('is_correct');
            $table->unsignedTinyInteger('xp_awarded')->default(0);
            $table->timestamp('answered_at');
            $table->string('idempotency_key')->unique();
            $table->timestamps();
        });

        Schema::create('daily_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedSmallInteger('minutes_studied')->default(0);
            $table->unsignedSmallInteger('goal_minutes')->default(10);
            $table->boolean('goal_met')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });

        Schema::create('leaderboard_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('week_start');
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('display_name');
            $table->unsignedInteger('xp')->default(0);
            $table->unsignedInteger('rank')->nullable();
            $table->timestamps();

            $table->unique(['week_start', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaderboard_entries');
        Schema::dropIfExists('daily_activities');
        Schema::dropIfExists('answers');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('study_sessions');
    }
};
