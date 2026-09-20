<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'nickname' => 'test-user',
            'email' => 'test@example.com',
            'birthdate' => '2000-01-01',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'nickname' => 'test-user',
            'birthdate' => '2000-01-01 00:00:00',
        ]);
        $response->assertNoContent();
    }
}
