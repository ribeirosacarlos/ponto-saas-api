<?php

namespace Tests\Feature\Auth;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthMeTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_timezone_in_expected_paths()
    {
        $company = Company::factory()->create(['timezone' => 'America/Sao_Paulo']);
        $user = User::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['user', 'roles', 'timezone']])
            ->assertJsonPath('timezone', 'America/Sao_Paulo')
            ->assertJsonPath('timeZone', 'America/Sao_Paulo')
            ->assertJsonPath('data.timezone', 'America/Sao_Paulo')
            ->assertJsonPath('data.timeZone', 'America/Sao_Paulo')
            ->assertJsonPath('data.user.timezone', 'America/Sao_Paulo')
            ->assertJsonPath('data.user.timeZone', 'America/Sao_Paulo')
            ->assertJsonPath('data.user.company.timezone', 'America/Sao_Paulo')
            ->assertJsonPath('data.user.company.timeZone', 'America/Sao_Paulo');
    }
}
