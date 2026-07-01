<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\EnsureCompanyHasAccess;
use App\Models\Announcement;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureCompanyHasAccess::class);
        $this->seedRoles();
    }

    public function test_admin_can_list_company_announcements(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createUser($company, 'admin');
        $announcement = $this->createAnnouncement($company, $admin);
        $this->createAnnouncement(Company::factory()->create(), User::factory()->create());

        $response = $this->actingAs($admin)->getJson('/v1/admin/announcements');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta']);

        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($announcement->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_admin_can_create_announcement(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createUser($company, 'admin');

        $response = $this->actingAs($admin)->postJson('/v1/admin/announcements', [
            'title' => 'Reunião de equipe',
            'type' => 'general',
            'sent_at' => now()->toDateTimeString(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Reunião de equipe');

        $this->assertDatabaseHas('announcements', [
            'company_id' => $company->id,
            'title' => 'Reunião de equipe',
        ]);
    }

    public function test_admin_can_update_announcement(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createUser($company, 'admin');
        $announcement = $this->createAnnouncement($company, $admin);

        $response = $this->actingAs($admin)->putJson("/v1/admin/announcements/{$announcement->id}", [
            'title' => 'Título Atualizado',
            'type' => 'general',
            'sent_at' => now()->toDateTimeString(),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Título Atualizado');
    }

    public function test_admin_can_delete_announcement(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createUser($company, 'admin');
        $announcement = $this->createAnnouncement($company, $admin);

        $this->actingAs($admin)
            ->deleteJson("/v1/admin/announcements/{$announcement->id}")
            ->assertOk();

        $this->assertDatabaseMissing('announcements', ['id' => $announcement->id]);
    }

    public function test_admin_cannot_delete_announcement_from_other_company(): void
    {
        $admin = $this->createUser(Company::factory()->create(), 'admin');
        $otherCompany = Company::factory()->create();
        $announcement = $this->createAnnouncement($otherCompany, User::factory()->create(['company_id' => $otherCompany->id]));

        $this->actingAs($admin)
            ->deleteJson("/v1/admin/announcements/{$announcement->id}")
            ->assertForbidden();
    }

    public function test_employee_cannot_create_announcement(): void
    {
        $company = Company::factory()->create();
        $employee = $this->createUser($company, 'employee');

        $this->actingAs($employee)
            ->postJson('/v1/admin/announcements', [
                'title' => 'Tentativa não autorizada',
                'type' => 'general',
                'sent_at' => now()->toDateTimeString(),
            ])
            ->assertForbidden();
    }

    private function createAnnouncement(Company $company, User $creator): Announcement
    {
        return Announcement::create([
            'company_id' => $company->id,
            'created_by' => $creator->id,
            'title' => 'Anúncio de teste',
            'type' => 'general',
            'sent_at' => now(),
        ]);
    }

    private function createUser(Company $company, string $role): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->syncRoles([$role]);

        return $user;
    }

    private function seedRoles(): void
    {
        foreach (['admin', 'manager', 'area_manager', 'employee'] as $roleName) {
            Role::updateOrCreate(
                ['name' => $roleName],
                ['display_name' => ucfirst(str_replace('_', ' ', $roleName))]
            );
        }
    }
}
