<?php

namespace Tests\Feature;

use App\Models\DailyActivity;
use App\Models\LeaderboardEntry;
use App\Models\StudyItem;
use App\Models\User;
use App\Models\UserItemProgress;
use Database\Seeders\N5CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_profile_details_and_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->patch('/api/user', [
            'nickname' => 'nihongo-learner',
            'birthdate' => '2000-01-01',
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
        ]);

        $response->assertOk()->assertJsonPath('nickname', 'nihongo-learner');
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'nickname' => 'nihongo-learner',
            'birthdate' => '2000-01-01 00:00:00',
        ]);
        Storage::disk('public')->assertExists($response->json('avatar'));
    }

    public function test_user_can_get_summary_and_delete_progress(): void
    {
        $this->seed(N5CatalogSeeder::class);
        $user = User::factory()->create();
        UserItemProgress::create(['user_id' => $user->id, 'study_item_id' => StudyItem::first()->id, 'status' => 'LEARNED']);
        DailyActivity::create(['user_id' => $user->id, 'date' => today()]);
        LeaderboardEntry::create(['user_id' => $user->id, 'week_start' => now()->startOfWeek(), 'display_name' => $user->nickname, 'xp' => 10]);
        $user->stats->update(['xp_total' => 10, 'streak_days' => 2, 'last_study_date' => today()]);

        $this->actingAs($user, 'sanctum')->getJson('/api/me/summary')
            ->assertOk()->assertJsonPath('weeklyRank', 1)->assertJsonPath('dailyGoalMinutes', 20);
        $this->actingAs($user, 'sanctum')->getJson('/api/leaderboard/weekly')
            ->assertOk()->assertJsonPath('weekStart', now()->startOfWeek()->toDateString())->assertJsonPath('daysLeft', max(0, 6 - now()->dayOfWeekIso + 1));
        $this->actingAs($user, 'sanctum')->deleteJson('/api/me/progress')->assertNoContent();
        $this->assertDatabaseCount('user_item_progress', 0);
        $this->assertDatabaseCount('daily_activities', 0);
        $this->assertDatabaseCount('leaderboard_entries', 0);
        $this->assertDatabaseHas('user_stats', ['user_id' => $user->id, 'level' => 1, 'xp_total' => 0, 'streak_days' => 0]);
        $this->app['auth']->forgetGuards();
        $this->deleteJson('/api/me/progress')->assertUnauthorized();
    }
}
