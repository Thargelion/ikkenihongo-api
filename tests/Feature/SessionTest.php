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

    private function word(string $surface, string $reading, array $meanings): StudyItem
    {
        return StudyItem::create([
            'source_key' => 'word:'.$surface.$reading,
            'type' => 'WORD',
            'jlpt_level' => 'N5',
            'surface' => $surface,
            'reading' => $reading,
            'meanings_es' => $meanings,
        ]);
    }

    private function answer(User $user, array $session, int $index, string $input, string $key)
    {
        return $this->actingAs($user, 'sanctum')->postJson("/api/sessions/{$session['id']}/answers", [
            'questionId' => $session['questions'][$index]['id'], 'rawInput' => $input, 'idempotencyKey' => $key,
        ]);
    }

    public function test_word_writing_accepts_any_word_with_the_same_meaning_and_katakana_forms(): void
    {
        $user = User::factory()->create();
        $friend = $this->word('友達', 'ともだち', ['Friend']);
        $this->word('友だち', 'ともだち', ['Friend']);
        $kilo = $this->word('キロ/キログラム', 'きろ', ['Kilo']);
        $copy = $this->word('コピーする', 'こぴーする', ['To copy']);
        $this->word('本', 'ほん', ['Book']);

        $session = $this->actingAs($user, 'sanctum')->postJson('/api/sessions', [
            'exercise' => 'WORD_WRITING', 'itemIds' => [$friend->id, $kilo->id, $copy->id],
        ])->assertCreated()->json();
        $byPrompt = collect($session['questions'])->keyBy('prompt');
        $this->assertSame('MEANING_TO_WORD', $byPrompt['Friend']['direction']);
        $this->assertArrayNotHasKey('choices', $byPrompt['Friend']);
        $idx = fn (string $prompt): int => array_search($prompt, array_column($session['questions'], 'prompt'), true);

        $this->answer($user, $session, $idx('Friend'), '友だち', 'k1')->assertJsonPath('feedbackCode', 'CORRECT');
        $this->answer($user, $session, $idx('Kilo'), 'キログラム', 'k2')->assertJsonPath('feedbackCode', 'CORRECT');
        $this->answer($user, $session, $idx('To copy'), 'こぴーする', 'k3')
            ->assertJsonPath('feedbackCode', 'INCORRECT')
            ->assertJsonPath('correctAnswer.surface', 'コピーする');
    }

    public function test_word_writing_marks_wrong_words_and_reveals_the_surface(): void
    {
        $user = User::factory()->create();
        $friend = $this->word('友達', 'ともだち', ['Friend']);
        $session = $this->actingAs($user, 'sanctum')->postJson('/api/sessions', [
            'exercise' => 'WORD_WRITING', 'itemIds' => [$friend->id],
        ])->assertCreated()->json();

        $this->answer($user, $session, 0, '先生', 'w1')
            ->assertJsonPath('feedbackCode', 'INCORRECT')
            ->assertJsonPath('correctAnswer.surface', '友達')
            ->assertJsonPath('correctAnswer.kana', 'ともだち');
    }

    public function test_multiple_choice_reading_stores_choices_and_grades_the_selected_one(): void
    {
        $user = User::factory()->create();
        $coffee = $this->word('コーヒー', 'こーひー', ['Coffee']);
        foreach ([['本', 'ほん'], ['水', 'みず'], ['山', 'やま'], ['川', 'かわ']] as [$surface, $reading]) {
            $this->word($surface, $reading, [$reading]);
        }

        $session = $this->actingAs($user, 'sanctum')->postJson('/api/sessions', [
            'exercise' => 'WORD_READING', 'format' => 'CHOICE', 'itemIds' => [$coffee->id],
        ])->assertCreated()->assertJsonPath('format', 'CHOICE')->json();
        $choices = $session['questions'][0]['choices'];

        $this->assertCount(4, $choices);
        $this->assertCount(4, array_unique($choices));
        $this->assertContains('こーひー', $choices);
        $this->actingAs($user, 'sanctum')->getJson("/api/sessions/{$session['id']}")
            ->assertJsonPath('questions.0.choices', $choices);
        $this->answer($user, $session, 0, 'こーひー', 'c1')->assertJsonPath('feedbackCode', 'CORRECT');
    }

    public function test_multiple_choice_writing_never_offers_a_synonym_as_distractor(): void
    {
        $user = User::factory()->create();
        $friend = $this->word('友達', 'ともだち', ['Friend']);
        $this->word('友だち', 'ともだち', ['Friend']);
        foreach ([['本', 'ほん'], ['水', 'みず'], ['山', 'やま'], ['川', 'かわ']] as [$surface, $reading]) {
            $this->word($surface, $reading, [$reading]);
        }

        $session = $this->actingAs($user, 'sanctum')->postJson('/api/sessions', [
            'exercise' => 'WORD_WRITING', 'format' => 'CHOICE', 'itemIds' => [$friend->id],
        ])->assertCreated()->json();
        $choices = $session['questions'][0]['choices'];

        $this->assertContains('友達', $choices);
        $this->assertNotContains('友だち', $choices);
        $this->answer($user, $session, 0, '友達', 'c1')->assertJsonPath('feedbackCode', 'CORRECT');
    }

    public function test_multiple_choice_is_only_available_for_word_exercises(): void
    {
        $this->seed(N5CatalogSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/sessions', [
            'exercise' => 'KANA_READING', 'format' => 'CHOICE', 'length' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('format');
    }

    public function test_reading_accepts_katakana_readings(): void
    {
        $user = User::factory()->create();
        $coffee = $this->word('コーヒー', 'コーヒー', ['Coffee']);
        $session = $this->actingAs($user, 'sanctum')->postJson('/api/sessions', [
            'exercise' => 'WORD_READING', 'itemIds' => [$coffee->id],
        ])->assertCreated()->json();

        $this->answer($user, $session, 0, 'コーヒー', 'r1')->assertJsonPath('feedbackCode', 'CORRECT');
    }

    public function test_word_writing_prompt_follows_the_requested_language(): void
    {
        $user = User::factory()->create();
        $word = StudyItem::create([
            'source_key' => 'word:lang', 'type' => 'WORD', 'jlpt_level' => 'N5', 'surface' => '本',
            'reading' => 'ほん', 'meanings_es' => ['Libro'], 'meanings_en' => ['Book'],
        ]);
        $create = fn (array $extra) => $this->actingAs($user, 'sanctum')->postJson('/api/sessions', [
            'exercise' => 'WORD_WRITING', 'itemIds' => [$word->id], ...$extra,
        ])->assertCreated()->json('questions.0.prompt');

        $this->assertSame('Libro', $create([]));
        $this->assertSame('Libro', $create(['lang' => 'es']));
        $this->assertSame('Book', $create(['lang' => 'en']));
        $this->actingAs($user, 'sanctum')->postJson('/api/sessions', ['exercise' => 'WORD_WRITING', 'lang' => 'fr'])
            ->assertUnprocessable()->assertJsonValidationErrors('lang');
    }

    public function test_idempotency_keys_are_scoped_to_each_user(): void
    {
        $this->seed(N5CatalogSeeder::class);
        $keyed = function (User $user) {
            $session = $this->actingAs($user, 'sanctum')->postJson('/api/sessions', [
                'exercise' => 'KANA_READING', 'length' => 1,
            ])->assertCreated()->json();
            $item = StudyItem::findOrFail($session['questions'][0]['itemId']);

            return $this->actingAs($user, 'sanctum')->postJson("/api/sessions/{$session['id']}/answers", [
                'questionId' => $session['questions'][0]['id'], 'rawInput' => $item->romaji[0], 'idempotencyKey' => 'shared-key',
            ]);
        };

        $keyed(User::factory()->create())->assertOk()->assertJsonPath('feedbackCode', 'CORRECT');
        $keyed(User::factory()->create())->assertOk()->assertJsonPath('feedbackCode', 'CORRECT');
        $this->assertDatabaseCount('answers', 2);
    }

    public function test_kana_writing_accepts_the_matching_syllabary_only(): void
    {
        $this->seed(N5CatalogSeeder::class);
        $user = User::factory()->create();
        $glyph = fn (string $script, string $kana) => StudyItem::where(['type' => 'KANA', 'script' => $script, 'glyph' => $kana])->firstOrFail();
        $ask = fn (string $script, StudyItem $item) => $this->actingAs($user, 'sanctum')->postJson('/api/sessions', [
            'exercise' => 'KANA_WRITING', 'script' => $script, 'itemIds' => [$item->id],
        ])->assertCreated()->json();

        $katakana = $ask('KATAKANA', $glyph('KATAKANA', 'ガ'));
        $this->answer($user, $katakana, 0, 'が', 'kw-1')->assertJsonPath('feedbackCode', 'WRONG_SCRIPT');
        $this->answer($user, $katakana, 0, 'ガ', 'kw-2')->assertJsonPath('feedbackCode', 'CORRECT');
        $this->answer($user, $ask('KATAKANA', $glyph('KATAKANA', 'ガ')), 0, 'ギ', 'kw-3')->assertJsonPath('feedbackCode', 'INCORRECT');

        $hiragana = $ask('HIRAGANA', $glyph('HIRAGANA', 'ぱ'));
        $this->answer($user, $hiragana, 0, 'パ', 'hw-1')->assertJsonPath('feedbackCode', 'WRONG_SCRIPT');
        $this->answer($user, $hiragana, 0, 'ぱ', 'hw-2')->assertJsonPath('feedbackCode', 'CORRECT');
    }
}
