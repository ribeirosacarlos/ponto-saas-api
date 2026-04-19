<?php

namespace Tests\Feature\Platform;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use RefreshDatabase;

    private function createRole(string $name, string $display): Role
    {
        return Role::updateOrCreate(['name' => $name], ['display_name' => $display]);
    }

    private function createSuperAdmin(): User
    {
        $role = $this->createRole('super_admin', 'Platform Administrator');
        $user = User::factory()->create(['company_id' => null]);
        $user->syncRoles([$role->name]);

        return $user;
    }

    public function test_super_admin_can_list_companies()
    {
        $this->createRole('admin', 'Administrator');
        Company::factory()->count(3)->create();

        $user = $this->createSuperAdmin();
        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/v1/platform/companies');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_admin_cannot_access_platform_companies()
    {
        $role = $this->createRole('admin', 'Administrator');
        $user = User::factory()->create();
        $user->syncRoles([$role->name]);

        Sanctum::actingAs($user, ['*']);

        $this->getJson('/v1/platform/companies')->assertStatus(403);
    }

    public function test_super_admin_can_block_company()
    {
        $user = $this->createSuperAdmin();
        Sanctum::actingAs($user, ['*']);

        $company = Company::factory()->create();

        $blockResponse = $this->postJson("/v1/platform/companies/{$company->id}/block", [
            'reason' => 'Non compliant',
        ]);

        $blockResponse->assertOk()
            ->assertJsonPath('data.is_blocked', true)
            ->assertJsonPath('data.blocked_reason', 'Non compliant');

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'is_blocked' => true,
            'blocked_reason' => 'Non compliant',
        ]);

    }

    public function test_super_admin_can_unblock_company()
    {
        $user = $this->createSuperAdmin();
        Sanctum::actingAs($user, ['*']);

        $company = Company::factory()->create([
            'is_blocked' => true,
            'blocked_at' => now(),
            'blocked_reason' => 'Non compliant',
        ]);

        $unblockResponse = $this->postJson("/v1/platform/companies/{$company->id}/unblock", []);

        $unblockResponse->assertOk()
            ->assertJsonPath('data.is_blocked', false)
            ->assertJsonPath('data.blocked_reason', null);

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'is_blocked' => false,
            'blocked_reason' => null,
        ]);
    }
}
