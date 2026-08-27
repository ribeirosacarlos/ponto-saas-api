<?php

namespace Tests\Feature\Commercial;

use App\Models\CommercialEmailSequence;
use App\Models\CommercialEmailTemplate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialEmailAdminCrudTest extends TestCase
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

    public function test_manager_can_create_email_template(): void
    {
        $manager = $this->userWithRole('commercial_manager');

        $response = $this->actingAs($manager)->postJson('/v1/admin/commercial/email/templates', [
            'name' => 'Primeiro contato',
            'slug' => 'primeiro-contato',
            'subject' => 'Olá {{contact_name}}',
            'body_html' => '<p>Olá {{first_name}}</p>',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.slug', 'primeiro-contato');
    }

    public function test_manager_can_create_sequence(): void
    {
        $manager = $this->userWithRole('commercial_manager');

        $response = $this->actingAs($manager)->postJson('/v1/admin/commercial/email/sequences', [
            'name' => 'Cold Outbound',
            'status' => 'active',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Cold Outbound');
    }

    public function test_manager_can_add_step_to_existing_sequence(): void
    {
        $manager = $this->userWithRole('commercial_manager');
        $sequence = CommercialEmailSequence::create(['name' => 'Cold Outbound', 'status' => 'active']);
        $template = CommercialEmailTemplate::create([
            'name' => 'Primeiro contato',
            'slug' => 'primeiro-contato',
            'subject' => 'Olá',
            'body_html' => '<p>Olá</p>',
        ]);

        $response = $this->actingAs($manager)->postJson("/v1/admin/commercial/email/sequences/{$sequence->id}/steps", [
            'template_id' => $template->id,
            'position' => 1,
            'delay_days' => 0,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.sequence_id', $sequence->id);
    }

    public function test_agent_cannot_create_email_template(): void
    {
        $agent = $this->userWithRole('commercial_agent');

        $this->actingAs($agent)->postJson('/v1/admin/commercial/email/templates', [
            'name' => 'Primeiro contato',
            'slug' => 'primeiro-contato',
            'subject' => 'Olá',
            'body_html' => '<p>Olá</p>',
        ])->assertForbidden();
    }

    public function test_manager_can_view_outreach_settings_defaults(): void
    {
        $manager = $this->userWithRole('commercial_manager');

        $response = $this->actingAs($manager)->getJson('/v1/admin/commercial/email/settings');

        $response->assertOk();
        $response->assertJsonPath('data.daily_send_limit', 80);
        $response->assertJsonPath('data.monthly_send_limit', 2400);
        $response->assertJsonPath('data.is_globally_paused', false);
    }

    public function test_manager_can_update_outreach_settings(): void
    {
        $manager = $this->userWithRole('commercial_manager');

        $response = $this->actingAs($manager)->putJson('/v1/admin/commercial/email/settings', [
            'daily_send_limit' => 50,
            'is_globally_paused' => true,
            'pause_reason' => 'Pausa manual para revisão',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.daily_send_limit', 50);
        $response->assertJsonPath('data.is_globally_paused', true);
    }
}
