<?php

namespace Tests\Feature\Commercial;

use App\Models\CommercialAffiliate;
use App\Models\CommercialAffiliateClick;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialAffiliateTest extends TestCase
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

    public function test_affiliate_can_be_created(): void
    {
        $manager = $this->userWithRole('commercial_manager');

        $response = $this->actingAs($manager)->postJson('/v1/admin/commercial/affiliates', [
            'name' => 'João Afiliado',
            'email' => 'joao@afiliados.test',
            'slug' => 'joao-afiliado',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas(
            (new CommercialAffiliate)->getTable(),
            ['slug' => 'joao-afiliado', 'name' => 'João Afiliado']
        );
    }

    public function test_affiliate_click_is_registered(): void
    {
        $affiliate = CommercialAffiliate::factory()->create(['slug' => 'parceiro-x']);

        $response = $this->postJson('/v1/commercial/track-affiliate-click', [
            'slug' => 'parceiro-x',
            'utm_source' => 'instagram',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas(
            (new CommercialAffiliateClick)->getTable(),
            ['affiliate_id' => $affiliate->id, 'utm_source' => 'instagram']
        );

        $click = CommercialAffiliateClick::where('affiliate_id', $affiliate->id)->first();
        $this->assertNotNull($click->ip_hash);
    }
}
