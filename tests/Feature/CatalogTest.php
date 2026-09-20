<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\N5CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_catalog_fields_and_kanji_detail(): void
    {
        $this->seed(N5CatalogSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/kana?script=HIRAGANA')
            ->assertOk()
            ->assertJsonCount(71)
            ->assertJsonPath('0.gridRow', 0)
            ->assertJsonPath('0.gridColumn', 0)
            ->assertJsonPath('0.romaji.0', 'a');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/kanji?jlpt=N5')
            ->assertOk()
            ->assertJsonCount(79)
            ->assertJsonPath('0.onyomi.0', 'ニチ')
            ->assertJsonPath('0.strokeCount', 4);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/words?jlpt=N5')
            ->assertOk()
            ->assertJsonCount(713)
            ->assertJsonPath('0.reading', 'あう');

        $this->actingAs($user, 'sanctum')->getJson('/api/kanji/'.urlencode('日'))
            ->assertOk()
            ->assertJsonPath('glyph', '日')
            ->assertJsonCount(2, 'examples');
        $this->actingAs($user, 'sanctum')->getJson('/api/kanji/'.urlencode('不存在'))->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/kanji')->assertUnauthorized();
    }
}
