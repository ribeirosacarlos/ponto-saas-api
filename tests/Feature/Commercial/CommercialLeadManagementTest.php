<?php

namespace Tests\Feature\Commercial;

use App\Models\CommercialLead;
use App\Models\CommercialLeadStep;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialLeadManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super_admin', 'commercial_manager', 'commercial_agent'] as $role) {
            Role::updateOrCreate(['name' => $role], ['display_name' => $role]);
        }
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['company_id' => null]);
        $user->syncRoles([$role]);

        return $user;
    }

    public function test_lead_can_be_created(): void
    {
        $manager = $this->userWithRole('commercial_manager');

        $response = $this->actingAs($manager)->postJson('/v1/admin/commercial/leads', [
            'company_name' => 'Acme Ltda',
            'email' => 'contato@acme.test',
            'priority' => 'high',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas(CommercialLead::query()->getModel()->getTable(), [
            'company_name' => 'Acme Ltda',
            'email' => 'contato@acme.test',
            'priority' => 'high',
            'status' => 'new',
        ]);
    }

    public function test_lead_can_move_step(): void
    {
        $manager = $this->userWithRole('commercial_manager');
        $lead = CommercialLead::factory()->create();
        $step = CommercialLeadStep::create(['name' => 'Ligação 1', 'position' => 2, 'active' => true]);

        $response = $this->actingAs($manager)->postJson("/v1/admin/commercial/leads/{$lead->id}/move-step", [
            'step_id' => $step->id,
            'note' => 'Cliente respondeu o primeiro contato.',
        ]);

        $response->assertOk();
        $this->assertSame($step->id, $lead->refresh()->current_step_id);
        $this->assertDatabaseHas(
            (new \App\Models\CommercialLeadStepLog)->getTable(),
            ['lead_id' => $lead->id, 'step_id' => $step->id, 'status' => 'done']
        );
    }

    public function test_note_can_be_added_to_lead(): void
    {
        $agent = $this->userWithRole('commercial_agent');
        $lead = CommercialLead::factory()->create(['assigned_to_user_id' => $agent->id]);

        $response = $this->actingAs($agent)->postJson("/v1/admin/commercial/leads/{$lead->id}/notes", [
            'note' => 'Lead pediu para retornar na próxima semana.',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas(
            (new \App\Models\CommercialLeadNote)->getTable(),
            ['lead_id' => $lead->id, 'user_id' => $agent->id]
        );
    }

    public function test_commercial_agent_cannot_add_note_to_lead_assigned_to_other_agent(): void
    {
        $agent = $this->userWithRole('commercial_agent');
        $otherAgent = $this->userWithRole('commercial_agent');
        $lead = CommercialLead::factory()->create(['assigned_to_user_id' => $otherAgent->id]);

        $this->actingAs($agent)
            ->postJson("/v1/admin/commercial/leads/{$lead->id}/notes", ['note' => 'Tentando acessar lead de outro agente.'])
            ->assertForbidden();
    }
}
