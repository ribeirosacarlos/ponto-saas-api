<?php

namespace Tests\Feature\Auth;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_token_with_valid_credentials(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'email' => 'user@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/v1/auth/login', [
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user', 'roles', 'token'])
            ->assertJsonPath('user.email', 'user@example.com');
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $company = Company::factory()->create();
        User::factory()->create([
            'company_id' => $company->id,
            'email' => 'user@example.com',
            'password' => bcrypt('correctpassword'),
        ]);

        $response = $this->postJson('/v1/auth/login', [
            'email' => 'user@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('message', 'Credenciais inválidas');
    }

    public function test_login_fails_with_nonexistent_user(): void
    {
        $response = $this->postJson('/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'anypassword',
        ]);

        $response->assertUnauthorized();
    }

    public function test_login_validates_required_fields(): void
    {
        $response = $this->postJson('/v1/auth/login', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_logout_revokes_token(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $token = $user->createToken('auth')->plainTextToken;

        $this->withToken($token)
            ->postJson('/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logout feito com sucesso');
    }
}
