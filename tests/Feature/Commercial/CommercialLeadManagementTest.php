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
            ->assertNotFound();
    }

    public function test_lead_creation_is_blocked_by_duplicate_email(): void
    {
        $manager = $this->userWithRole('commercial_manager');
        CommercialLead::factory()->create(['email' => 'contato@acme.test']);

        $response = $this->actingAs($manager)->postJson('/v1/admin/commercial/leads', [
            'company_name' => 'Acme Filial 2',
            'email' => 'Contato@Acme.test',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('email');
        $this->assertSame(1, CommercialLead::query()->where('email', 'contato@acme.test')->count());
    }

    public function test_lead_creation_is_blocked_by_duplicate_phone_regardless_of_formatting(): void
    {
        $manager = $this->userWithRole('commercial_manager');
        CommercialLead::factory()->create(['phone' => '+55 (11) 99999-9999']);

        $response = $this->actingAs($manager)->postJson('/v1/admin/commercial/leads', [
            'company_name' => 'Outra Empresa',
            'phone' => '55 11 99999-9999',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('phone');
    }

    public function test_lead_creation_is_blocked_by_duplicate_google_maps_place_id(): void
    {
        $manager = $this->userWithRole('commercial_manager');
        CommercialLead::factory()->create(['google_maps_place_id' => 'ChIJN1t_tDeuEmsRUsoyG83frY4']);

        $response = $this->actingAs($manager)->postJson('/v1/admin/commercial/leads', [
            'company_name' => 'Outra Empresa',
            'google_maps_place_id' => 'ChIJN1t_tDeuEmsRUsoyG83frY4',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('google_maps_place_id');
    }

    public function test_lead_creation_is_not_blocked_when_no_field_matches(): void
    {
        $manager = $this->userWithRole('commercial_manager');
        CommercialLead::factory()->create([
            'email' => 'contato@acme.test',
            'phone' => '11999999999',
            'google_maps_place_id' => 'ChIJN1t_tDeuEmsRUsoyG83frY4',
        ]);

        $response = $this->actingAs($manager)->postJson('/v1/admin/commercial/leads', [
            'company_name' => 'Empresa Diferente',
            'email' => 'outro@empresa.test',
            'phone' => '11988887777',
            'google_maps_place_id' => 'ChIJOutroPlaceId123',
        ]);

        $response->assertCreated();
    }

    public function test_lead_update_is_blocked_when_new_email_belongs_to_another_lead(): void
    {
        $manager = $this->userWithRole('commercial_manager');
        CommercialLead::factory()->create(['email' => 'contato@acme.test']);
        $lead = CommercialLead::factory()->create(['email' => 'lead2@acme.test']);

        $response = $this->actingAs($manager)->putJson("/v1/admin/commercial/leads/{$lead->id}", [
            'email' => 'contato@acme.test',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_lead_update_keeping_its_own_email_is_allowed(): void
    {
        $manager = $this->userWithRole('commercial_manager');
        $lead = CommercialLead::factory()->create(['email' => 'contato@acme.test']);

        $response = $this->actingAs($manager)->putJson("/v1/admin/commercial/leads/{$lead->id}", [
            'email' => 'contato@acme.test',
            'contact_name' => 'Novo Contato',
        ]);

        $response->assertOk();
    }

    public function test_multiple_leads_can_be_created_in_bulk(): void
    {
        $manager = $this->userWithRole('commercial_manager');

        $response = $this->actingAs($manager)->postJson('/v1/admin/commercial/leads/bulk', [
            'leads' => [
                ['company_name' => 'Empresa Um', 'email' => 'um@empresa.test'],
                ['company_name' => 'Empresa Dois', 'email' => 'dois@empresa.test'],
                ['company_name' => 'Empresa Três', 'email' => 'tres@empresa.test'],
            ],
        ]);

        $response->assertStatus(207);
        $response->assertJsonPath('meta.total', 3);
        $response->assertJsonPath('meta.created', 3);
        $response->assertJsonPath('meta.failed', 0);
        $response->assertJsonPath('data.0.status', 'created');
        $response->assertJsonPath('data.1.status', 'created');
        $response->assertJsonPath('data.2.status', 'created');

        $this->assertSame(3, CommercialLead::query()->count());
    }

    public function test_bulk_lead_creation_reports_partial_success_on_duplicate(): void
    {
        $manager = $this->userWithRole('commercial_manager');
        CommercialLead::factory()->create(['email' => 'existente@empresa.test']);

        $response = $this->actingAs($manager)->postJson('/v1/admin/commercial/leads/bulk', [
            'leads' => [
                ['company_name' => 'Empresa Válida', 'email' => 'valida@empresa.test'],
                ['company_name' => 'Empresa Duplicada', 'email' => 'existente@empresa.test'],
            ],
        ]);

        $response->assertStatus(207);
        $response->assertJsonPath('meta.total', 2);
        $response->assertJsonPath('meta.created', 1);
        $response->assertJsonPath('meta.failed', 1);
        $response->assertJsonPath('data.0.status', 'created');
        $response->assertJsonPath('data.1.status', 'error');
        $response->assertJsonPath('data.1.errors.email.0', 'Já existe um lead cadastrado com este e-mail.');

        $this->assertSame(1, CommercialLead::query()->where('email', 'existente@empresa.test')->count());
        $this->assertSame(1, CommercialLead::query()->where('email', 'valida@empresa.test')->count());
    }

    public function test_bulk_lead_creation_requires_leads_array(): void
    {
        $manager = $this->userWithRole('commercial_manager');

        $this->actingAs($manager)
            ->postJson('/v1/admin/commercial/leads/bulk', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('leads');
    }

    public function test_bulk_lead_creation_respects_max_items_limit(): void
    {
        $manager = $this->userWithRole('commercial_manager');

        $leads = array_map(
            fn ($i) => ['company_name' => "Empresa {$i}"],
            range(1, 101)
        );

        $this->actingAs($manager)
            ->postJson('/v1/admin/commercial/leads/bulk', ['leads' => $leads])
            ->assertStatus(422)
            ->assertJsonValidationErrors('leads');
    }
}
