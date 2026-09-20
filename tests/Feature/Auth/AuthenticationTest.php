<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertNoContent();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertNoContent();
    }

    public function test_guest_can_upgrade_without_losing_its_id(): void
    {
        $this->post('/guest')->assertNoContent();
        $guest = auth()->user();

        $this->actingAs($guest, 'sanctum')->postJson('/api/me/upgrade', [
            'name' => 'Real User', 'nickname' => 'real-user', 'email' => 'real@example.com',
            'password' => 'password', 'password_confirmation' => 'password',
        ])->assertOk()->assertJsonPath('id', $guest->id)->assertJsonPath('is_guest', false);

        $this->actingAs(User::factory()->create(), 'sanctum')->postJson('/api/me/upgrade', [
            'name' => 'No Guest', 'nickname' => 'no-guest', 'email' => 'no@example.com',
            'password' => 'password', 'password_confirmation' => 'password',
        ])->assertStatus(409);
        $this->post('/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/me/upgrade', [])->assertUnauthorized();
    }
}
