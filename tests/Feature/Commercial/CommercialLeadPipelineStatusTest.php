<?php

namespace Tests\Feature\Commercial;

use App\Models\CommercialLead;
use App\Models\CommercialLeadStep;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CommercialLeadStepsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialLeadPipelineStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::updateOrCreate(['name' => 'super_admin'], ['display_name' => 'super_admin']);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create(['company_id' => null]);
        $user->syncRoles(['super_admin']);

        return $user;
    }

    public function test_seeder_creates_fourteen_ordered_active_steps(): void
    {
        (new CommercialLeadStepsSeeder)->run();

        $steps = CommercialLeadStep::query()->where('active', true)->orderBy('position')->get();

        $this->assertCount(14, $steps);
        $this->assertSame('lead-novo', $steps->first()->slug);
        $this->assertSame('fechado-perdido', $steps->last()->slug);
        $this->assertTrue($steps->firstWhere('slug', 'fechado-ganho')->is_final);
        $this->assertTrue($steps->firstWhere('slug', 'fechado-perdido')->is_final);
    }

    public function test_seeder_is_idempotent_and_deactivates_legacy_steps(): void
    {
        CommercialLeadStep::create(['name' => 'WhatsApp inicial', 'position' => 1, 'active' => true]);

        (new CommercialLeadStepsSeeder)->run();
        (new CommercialLeadStepsSeeder)->run();

        $this->assertSame(14, CommercialLeadStep::query()->where('active', true)->count());
        $this->assertSame(
            false,
            CommercialLeadStep::query()->where('name', 'WhatsApp inicial')->first()->active,
        );
    }

    public function test_moving_step_sets_current_step_started_at(): void
    {
        (new CommercialLeadStepsSeeder)->run();

        $lead = CommercialLead::factory()->create();
        $step = CommercialLeadStep::where('slug', 'whatsapp-1')->first();

        $lead->update(['current_step_id' => $step->id]);

        $this->assertNotNull($lead->fresh()->current_step_started_at);
    }

    public function test_stage_is_overdue_and_warns_about_next_stage(): void
    {
        (new CommercialLeadStepsSeeder)->run();

        $step = CommercialLeadStep::where('slug', 'whatsapp-1')->first();
        $lead = CommercialLead::factory()->create(['current_step_id' => $step->id]);

        $this->travelTo(now()->addDays($step->default_due_days + 1));

        $status = $lead->fresh()->pipelineStatus();

        $this->assertTrue($status['current_stage_is_overdue']);
        $this->assertSame(
            'Tempo padrão desta etapa encerrado. Tente avançar para a próxima etapa do pipeline: Ligação 1.',
            $status['current_stage_warning_message'],
        );
        $this->assertSame('Ligação 1', $status['next_stage_name']);
    }

    public function test_final_stage_never_warns_even_when_long_overdue(): void
    {
        (new CommercialLeadStepsSeeder)->run();

        $step = CommercialLeadStep::where('slug', 'fechado-ganho')->first();
        $lead = CommercialLead::factory()->create(['current_step_id' => $step->id]);

        $this->travelTo(now()->addDays(365));

        $status = $lead->fresh()->pipelineStatus();

        $this->assertFalse($status['current_stage_is_overdue']);
        $this->assertNull($status['current_stage_warning_message']);
    }

    public function test_stage_with_zero_default_days_never_generates_due_date(): void
    {
        (new CommercialLeadStepsSeeder)->run();

        $step = CommercialLeadStep::where('slug', 'demo-agendada')->first();
        $lead = CommercialLead::factory()->create(['current_step_id' => $step->id]);

        $this->travelTo(now()->addDays(365));

        $status = $lead->fresh()->pipelineStatus();

        $this->assertNull($status['current_stage_due_at']);
        $this->assertFalse($status['current_stage_is_overdue']);
        $this->assertNull($status['current_stage_warning_message']);
    }

    public function test_last_stage_without_next_never_warns_even_if_overdue(): void
    {
        (new CommercialLeadStepsSeeder)->run();

        // Simulate a hypothetical last non-final stage with a real SLA, to isolate the
        // "no next active stage" rule from the "is_final" and "default_due_days = 0" rules.
        CommercialLeadStep::where('slug', 'fechado-perdido')->update([
            'is_final' => false,
            'default_due_days' => 5,
        ]);

        $step = CommercialLeadStep::where('slug', 'fechado-perdido')->first();
        $lead = CommercialLead::factory()->create(['current_step_id' => $step->id]);

        // current_step_started_at is intentionally not fillable (only auto-set on step
        // change), so back-date it directly to simulate an old, overdue entry.
        $lead->current_step_started_at = now()->subDays(6);
        $lead->save();

        $status = $lead->fresh()->pipelineStatus();

        $this->assertTrue($status['current_stage_is_overdue']);
        $this->assertNull($status['next_stage_id']);
        $this->assertNull($status['current_stage_warning_message']);
    }

    public function test_scope_overdue_filters_leads_at_query_level(): void
    {
        (new CommercialLeadStepsSeeder)->run();

        $step = CommercialLeadStep::where('slug', 'whatsapp-1')->first();

        $overdueLead = CommercialLead::factory()->create(['current_step_id' => $step->id]);
        $overdueLead->current_step_started_at = now()->subDays($step->default_due_days + 1);
        $overdueLead->save();

        $onTimeLead = CommercialLead::factory()->create(['current_step_id' => $step->id]);

        $overdueIds = CommercialLead::query()->overdue(true)->pluck('id');
        $onTimeIds = CommercialLead::query()->overdue(false)->pluck('id');

        $this->assertTrue($overdueIds->contains($overdueLead->id));
        $this->assertFalse($overdueIds->contains($onTimeLead->id));
        $this->assertTrue($onTimeIds->contains($onTimeLead->id));
        $this->assertFalse($onTimeIds->contains($overdueLead->id));
    }

    public function test_admin_can_filter_overdue_leads_via_api(): void
    {
        (new CommercialLeadStepsSeeder)->run();
        $admin = $this->superAdmin();

        $step = CommercialLeadStep::where('slug', 'whatsapp-1')->first();

        $overdueLead = CommercialLead::factory()->create(['current_step_id' => $step->id]);
        $overdueLead->current_step_started_at = now()->subDays($step->default_due_days + 1);
        $overdueLead->save();

        $onTimeLead = CommercialLead::factory()->create(['current_step_id' => $step->id]);

        $response = $this->actingAs($admin)->getJson('/v1/admin/commercial/leads?is_overdue=true');
        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($overdueLead->id));
        $this->assertFalse($ids->contains($onTimeLead->id));
    }

    public function test_admin_can_filter_on_time_leads_via_api(): void
    {
        (new CommercialLeadStepsSeeder)->run();
        $admin = $this->superAdmin();

        $step = CommercialLeadStep::where('slug', 'whatsapp-1')->first();

        $overdueLead = CommercialLead::factory()->create(['current_step_id' => $step->id]);
        $overdueLead->current_step_started_at = now()->subDays($step->default_due_days + 1);
        $overdueLead->save();

        $onTimeLead = CommercialLead::factory()->create(['current_step_id' => $step->id]);

        $response = $this->actingAs($admin)->getJson('/v1/admin/commercial/leads?is_overdue=false');
        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($onTimeLead->id));
        $this->assertFalse($ids->contains($overdueLead->id));
    }
}
