<?php

namespace Tests\Feature;

use App\Models\StudyItem;
use App\Models\User;
use Database\Seeders\N5CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_answer_a_kana_question_idempotently(): void
    {
        $this->seed(N5CatalogSeeder::class);
        $user = User::factory()->create();
        $session = $this->actingAs($user, 'sanctum')->postJson('/api/sessions', [
            'exercise' => 'KANA_READING',
            'length' => 1,
        ])->assertCreated()->json();
        $question = $session['questions'][0];
        $this->assertArrayHasKey('itemId', $question);
        $item = StudyItem::where('glyph', $question['prompt'])->firstOrFail();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/sessions/{$session['id']}/answers", [
                'questionId' => $question['id'],
                'rawInput' => $question['prompt'],
                'idempotencyKey' => 'wrong-script-retry',
            ])
            ->assertOk()
            ->assertJsonPath('feedbackCode', 'WRONG_SCRIPT');

        $payload = [
            'questionId' => $question['id'],
            'rawInput' => $item->romaji[0],
            'idempotencyKey' => 'wrong-script-retry',
        ];

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/sessions/{$session['id']}/answers", $payload)
            ->assertOk()
            ->assertJsonPath('feedbackCode', 'CORRECT')
            ->assertJsonPath('questionId', $question['id'])
            ->assertJsonPath('xpAwarded', 1);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/sessions/{$session['id']}/answers", $payload)
            ->assertOk()
            ->assertJsonPath('feedbackCode', 'CORRECT');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/sessions/{$session['id']}/answers", array_merge($payload, ['idempotencyKey' => 'different-key']))
            ->assertStatus(409)
            ->assertJsonPath('message', 'Question already answered.');

        $this->assertDatabaseCount('answers', 1);
        $this->assertDatabaseHas('user_item_progress', [
            'user_id' => $user->id,
            'study_item_id' => $item->id,
            'times_seen' => 1,
            'times_correct' => 1,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/sessions/{$session['id']}/complete")
            ->assertOk()
            ->assertJsonPath('xpEarned', 1)
            ->assertJsonPath('length', 1)
            ->assertJsonPath('streakDays', 1);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/me/stats')
            ->assertOk()
            ->assertJsonPath('xp_total', 1);
    }

    public function test_sessions_support_katakana_item_selection_and_sync_errors(): void
    {
        $this->seed(N5CatalogSeeder::class);
        $user = User::factory()->create();
        $item = StudyItem::where(['type' => 'KANA', 'script' => 'KATAKANA', 'glyph' => 'ア'])->firstOrFail();
        $session = $this->actingAs($user, 'sanctum')->postJson('/api/sessions', [
            'exercise' => 'KANA_READING', 'script' => 'KATAKANA', 'length' => 10, 'itemIds' => [$item->id],
        ])->assertCreated()->assertJsonPath('length', 1)->json();
        $question = $session['questions'][0];

        $this->actingAs($user, 'sanctum')->postJson("/api/sessions/{$session['id']}/answers", [
            'questionId' => $question['id'], 'rawInput' => '日', 'idempotencyKey' => 'kanji-retry',
        ])->assertOk()->assertJsonPath('feedbackCode', 'WRONG_SCRIPT');
        $this->actingAs($user, 'sanctum')->postJson("/api/sessions/{$session['id']}/answers:sync", [])->assertNotFound();
        $this->actingAs($user, 'sanctum')->postJson("/api/sessions/{$session['id']}/answers/sync", ['answers' => [
            ['questionId' => $question['id'], 'rawInput' => '', 'idempotencyKey' => 'empty-retry'],
            ['questionId' => $question['id'], 'rawInput' => 'a', 'idempotencyKey' => 'empty-retry'],
            ['questionId' => $question['id'], 'rawInput' => 'a', 'idempotencyKey' => 'other-key'],
        ]])->assertOk()->assertJsonPath('results.0.feedbackCode', 'EMPTY')->assertJsonPath('results.1.feedbackCode', 'CORRECT')->assertJsonPath('results.2.error.status', 409)->assertJsonPath('results.2.error.message', 'Question already answered.');

        $this->actingAs($user, 'sanctum')->postJson('/api/sessions', [
            'exercise' => 'KANA_READING', 'itemIds' => ['00000000-0000-0000-0000-000000000000'],
        ])->assertUnprocessable();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/sessions', ['exercise' => 'KANA_READING'])->assertUnauthorized();
    }

    public function test_kanji_item_selection_includes_example_words(): void
    {
        $this->seed(N5CatalogSeeder::class);
        $user = User::factory()->create();
        $kanji = StudyItem::where(['type' => 'KANJI', 'glyph' => '日'])->firstOrFail();

        $session = $this->actingAs($user, 'sanctum')->postJson('/api/sessions', [
            'exercise' => 'KANJI_READING', 'itemIds' => [$kanji->id], 'length' => 3,
        ])->assertCreated()->assertJsonPath('length', 3)->json();

        $items = StudyItem::whereIn('id', collect($session['questions'])->pluck('itemId'))->pluck('type');
        $this->assertTrue($items->contains('KANJI'));
        $this->assertTrue($items->contains('WORD'));
    }
}
