<?php

namespace Tests\Feature\Auth;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_returns_success_for_existing_email(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        User::factory()->create([
            'company_id' => $company->id,
            'email' => 'user@example.com',
        ]);

        $response = $this->postJson('/v1/forgot-password', [
            'email' => 'user@example.com',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message']);
    }

    public function test_forgot_password_returns_success_even_for_nonexistent_email(): void
    {
        Queue::fake();

        $response = $this->postJson('/v1/forgot-password', [
            'email' => 'nobody@example.com',
        ]);

        // Prevents email enumeration
        $response->assertOk()
            ->assertJsonStructure(['message']);
    }

    public function test_forgot_password_validates_email_field(): void
    {
        $response = $this->postJson('/v1/forgot-password', ['email' => 'not-an-email']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_reset_password_with_valid_token(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'email' => 'user@example.com',
        ]);

        $plainToken = 'VALIDTOKEN123';
        DB::table('password_reset_tokens')->insert([
            'email' => 'user@example.com',
            'token' => hash('sha256', $plainToken),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/v1/reset-password', [
            'token' => $plainToken,
            'email' => 'user@example.com',
            'password' => 'NewPass1234',
            'password_confirmation' => 'NewPass1234',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Senha redefinida com sucesso.');

        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'user@example.com',
        ]);
    }

    public function test_reset_password_fails_with_invalid_token(): void
    {
        $company = Company::factory()->create();
        User::factory()->create([
            'company_id' => $company->id,
            'email' => 'user@example.com',
        ]);

        DB::table('password_reset_tokens')->insert([
            'email' => 'user@example.com',
            'token' => hash('sha256', 'CORRECTTOKEN'),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/v1/reset-password', [
            'token' => 'WRONGTOKEN',
            'email' => 'user@example.com',
            'password' => 'NewPass1234',
            'password_confirmation' => 'NewPass1234',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Token inválido ou expirado.');
    }

    public function test_reset_password_fails_when_no_token_exists(): void
    {
        $response = $this->postJson('/v1/reset-password', [
            'token' => 'ANYTOKEN',
            'email' => 'nobody@example.com',
            'password' => 'NewPass1234',
            'password_confirmation' => 'NewPass1234',
        ]);

        $response->assertUnprocessable();
    }
}
