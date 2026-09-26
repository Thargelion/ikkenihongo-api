<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogle(array $attributes = [], bool $verified = true): void
    {
        $google = (new GoogleUser)->setRaw(['email_verified' => $verified])->map([
            'id' => 'g-123',
            'name' => 'Aiko Tanaka',
            'nickname' => null,
            'email' => 'aiko@example.com',
        ] + $attributes);
        $google->user = ['email_verified' => $verified];

        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('user')->andReturn($google);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_redirect_sends_user_to_google(): void
    {
        config(['services.google' => ['client_id' => 'id', 'client_secret' => 'secret', 'redirect' => 'http://localhost/auth/google/callback']]);

        $this->get('/auth/google/redirect')->assertRedirectContains('accounts.google.com');
    }

    public function test_new_google_user_is_created_and_logged_in(): void
    {
        $this->fakeGoogle();

        $this->get('/auth/google/callback')->assertRedirect(config('app.frontend_url').'/home');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'aiko@example.com', 'google_id' => 'g-123', 'is_guest' => false]);
    }

    public function test_guest_is_upgraded_in_place(): void
    {
        $guest = User::factory()->create(['is_guest' => true]);
        $this->fakeGoogle();

        $this->actingAs($guest)->get('/auth/google/callback');

        $this->assertAuthenticatedAs($guest->fresh());
        $this->assertDatabaseHas('users', ['id' => $guest->id, 'email' => 'aiko@example.com', 'is_guest' => false]);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_existing_account_is_linked_and_guest_is_dropped(): void
    {
        $existing = User::factory()->create(['email' => 'aiko@example.com', 'email_verified_at' => now()]);
        $guest = User::factory()->create(['is_guest' => true]);
        $this->fakeGoogle();

        $this->actingAs($guest)->get('/auth/google/callback');

        $this->assertAuthenticatedAs($existing->fresh());
        $this->assertSame('g-123', $existing->fresh()->google_id);
        $this->assertDatabaseMissing('users', ['id' => $guest->id]);
    }

    public function test_linking_an_unverified_account_rotates_its_password(): void
    {
        $existing = User::factory()->unverified()->create(['email' => 'aiko@example.com']);
        $oldHash = $existing->password;
        $this->fakeGoogle();

        $this->get('/auth/google/callback');

        $this->assertNotSame($oldHash, $existing->fresh()->password);
        $this->assertNotNull($existing->fresh()->email_verified_at);
    }

    public function test_unverified_google_email_is_rejected(): void
    {
        $this->fakeGoogle(verified: false);

        $this->get('/auth/google/callback')->assertRedirect(config('app.frontend_url').'/sign-in?error=google');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_google_email_is_matched_case_insensitively(): void
    {
        $existing = User::factory()->create(['email' => 'aiko@example.com', 'email_verified_at' => now()]);
        $this->fakeGoogle(['email' => 'Aiko@Example.com']);

        $this->get('/auth/google/callback');

        $this->assertAuthenticatedAs($existing->fresh());
        $this->assertDatabaseCount('users', 1);
    }

    public function test_google_id_is_not_exposed_in_user_responses(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['google_id' => 'g-123'])->save();

        $this->actingAs($user)->getJson('/api/user')->assertJsonMissingPath('google_id');
    }
}
