<?php

namespace Tests\Feature\Commercial;

use App\Models\CommercialLead;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialLeadAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super_admin', 'admin', 'commercial_manager', 'commercial_agent'] as $role) {
            Role::updateOrCreate(['name' => $role], ['display_name' => $role]);
        }
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['company_id' => null]);
        $user->syncRoles([$role]);

        return $user;
    }

    public function test_super_admin_lists_all_leads(): void
    {
        $agent1 = $this->userWithRole('commercial_agent');
        $agent2 = $this->userWithRole('commercial_agent');

        CommercialLead::factory()->count(2)->create(['assigned_to_user_id' => $agent1->id]);
        CommercialLead::factory()->count(3)->create(['assigned_to_user_id' => $agent2->id]);

        $superAdmin = $this->userWithRole('super_admin');

        $response = $this->actingAs($superAdmin)->getJson('/v1/admin/commercial/leads');

        $response->assertOk();
        $this->assertSame(5, $response->json('meta.total'));
    }

    public function test_commercial_manager_lists_all_leads(): void
    {
        $agent1 = $this->userWithRole('commercial_agent');
        $agent2 = $this->userWithRole('commercial_agent');

        CommercialLead::factory()->count(2)->create(['assigned_to_user_id' => $agent1->id]);
        CommercialLead::factory()->count(3)->create(['assigned_to_user_id' => $agent2->id]);

        $manager = $this->userWithRole('commercial_manager');

        $response = $this->actingAs($manager)->getJson('/v1/admin/commercial/leads');

        $response->assertOk();
        $this->assertSame(5, $response->json('meta.total'));
    }

    public function test_commercial_agent_lists_only_assigned_leads(): void
    {
        $agent = $this->userWithRole('commercial_agent');
        $otherAgent = $this->userWithRole('commercial_agent');

        $ownLeads = CommercialLead::factory()->count(2)->create(['assigned_to_user_id' => $agent->id]);
        CommercialLead::factory()->count(4)->create(['assigned_to_user_id' => $otherAgent->id]);

        $response = $this->actingAs($agent)->getJson('/v1/admin/commercial/leads');

        $response->assertOk();
        $this->assertSame(2, $response->json('meta.total'));

        $returnedIds = collect($response->json('data'))->pluck('id')->sort()->values();
        $expectedIds = $ownLeads->pluck('id')->sort()->values();
        $this->assertEquals($expectedIds->all(), $returnedIds->all());
    }

    public function test_commercial_agent_cannot_access_affiliates(): void
    {
        $agent = $this->userWithRole('commercial_agent');

        $this->actingAs($agent)
            ->getJson('/v1/admin/commercial/affiliates')
            ->assertForbidden();
    }

    public function test_commercial_agent_cannot_access_commissions(): void
    {
        $agent = $this->userWithRole('commercial_agent');

        $this->actingAs($agent)
            ->getJson('/v1/admin/commercial/commissions')
            ->assertForbidden();
    }

    public function test_plain_admin_cannot_access_commercial_leads(): void
    {
        // O módulo comercial é da plataforma inteira (sem company_id), então
        // um admin (escopado à própria empresa) não deve enxergá-lo — só super_admin.
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->getJson('/v1/admin/commercial/leads')
            ->assertForbidden();
    }

    public function test_plain_admin_cannot_access_commercial_affiliates(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->getJson('/v1/admin/commercial/affiliates')
            ->assertForbidden();
    }

    public function test_commercial_agent_cannot_view_lead_assigned_to_another_agent(): void
    {
        $agent = $this->userWithRole('commercial_agent');
        $otherAgent = $this->userWithRole('commercial_agent');

        $lead = CommercialLead::factory()->create(['assigned_to_user_id' => $otherAgent->id]);

        // A query já é filtrada por assigned_to_user_id, então o lead de outro
        // agente nem aparece (404), evitando vazar a existência do recurso.
        $this->actingAs($agent)
            ->getJson("/v1/admin/commercial/leads/{$lead->id}")
            ->assertNotFound();
    }
}
