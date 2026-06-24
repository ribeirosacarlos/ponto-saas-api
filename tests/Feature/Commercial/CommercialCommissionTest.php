<?php

namespace Tests\Feature\Commercial;

use App\Models\CommercialAffiliate;
use App\Models\CommercialCommission;
use App\Models\CommercialCommissionPlan;
use App\Models\CommercialLead;
use App\Models\Company;
use App\Services\Commercial\CommercialAffiliateBonusService;
use App\Services\Commercial\CommercialCommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialCommissionTest extends TestCase
{
    use RefreshDatabase;

    private function defaultPlan(): CommercialCommissionPlan
    {
        return CommercialCommissionPlan::create([
            'name' => 'Plano padrão afiliados',
            'commission_type' => 'recurring_percentage',
            'commission_percentage' => 20,
            'recurrence_months' => 6,
            'bonus_enabled' => true,
            'bonus_every_clients' => 5,
            'bonus_amount' => 50,
            'active' => true,
        ]);
    }

    public function test_default_commission_uses_20_percent(): void
    {
        $plan = $this->defaultPlan();
        $affiliate = CommercialAffiliate::factory()->create(['commission_plan_id' => $plan->id]);
        $lead = CommercialLead::factory()->create(['affiliate_id' => $affiliate->id]);

        $commissions = app(CommercialCommissionService::class)->generateRecurringCommissions($lead, 100.0);

        $this->assertSame(20.0, (float) $commissions->first()->commission_percentage);
        $this->assertEquals(20.0, (float) $commissions->first()->commission_amount);
    }

    public function test_default_commission_lasts_6_months(): void
    {
        $plan = $this->defaultPlan();
        $affiliate = CommercialAffiliate::factory()->create(['commission_plan_id' => $plan->id]);
        $lead = CommercialLead::factory()->create(['affiliate_id' => $affiliate->id]);

        $commissions = app(CommercialCommissionService::class)->generateRecurringCommissions($lead, 100.0);

        $this->assertCount(6, $commissions);
        $this->assertEquals([1, 2, 3, 4, 5, 6], $commissions->pluck('month_number')->all());
    }

    public function test_bonus_generates_50_euros_for_every_5_paid_clients_in_month(): void
    {
        $plan = $this->defaultPlan();
        $affiliate = CommercialAffiliate::factory()->create(['commission_plan_id' => $plan->id]);

        $paidAt = now();

        for ($i = 0; $i < 5; $i++) {
            $lead = CommercialLead::factory()->create(['affiliate_id' => $affiliate->id]);

            CommercialCommission::create([
                'affiliate_id' => $affiliate->id,
                'lead_id' => $lead->id,
                'customer_id' => Company::factory()->create()->id,
                'commission_plan_id' => $plan->id,
                'base_amount' => 100,
                'commission_percentage' => 20,
                'commission_amount' => 20,
                'month_number' => 1,
                'status' => 'paid',
                'paid_at' => $paidAt,
            ]);
        }

        $bonus = app(CommercialAffiliateBonusService::class)
            ->recalculateForMonth($affiliate->id, $paidAt->year, $paidAt->month);

        $this->assertNotNull($bonus);
        $this->assertSame(5, $bonus->clients_count);
        $this->assertEquals(50.0, (float) $bonus->total_bonus_amount);
    }

    public function test_bonus_generates_100_euros_for_10_paid_clients_in_month(): void
    {
        $plan = $this->defaultPlan();
        $affiliate = CommercialAffiliate::factory()->create(['commission_plan_id' => $plan->id]);

        $paidAt = now();

        for ($i = 0; $i < 10; $i++) {
            $lead = CommercialLead::factory()->create(['affiliate_id' => $affiliate->id]);

            CommercialCommission::create([
                'affiliate_id' => $affiliate->id,
                'lead_id' => $lead->id,
                'customer_id' => Company::factory()->create()->id,
                'commission_plan_id' => $plan->id,
                'base_amount' => 100,
                'commission_percentage' => 20,
                'commission_amount' => 20,
                'month_number' => 1,
                'status' => 'paid',
                'paid_at' => $paidAt,
            ]);
        }

        $bonus = app(CommercialAffiliateBonusService::class)
            ->recalculateForMonth($affiliate->id, $paidAt->year, $paidAt->month);

        $this->assertEquals(100.0, (float) $bonus->total_bonus_amount);
    }
}
