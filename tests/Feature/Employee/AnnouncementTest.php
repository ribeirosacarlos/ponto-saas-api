<?php

namespace Tests\Feature\Employee;

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

    public function test_employee_can_list_company_announcements(): void
    {
        $company = Company::factory()->create();
        $employee = $this->createUser($company, 'employee');
        $announcement = $this->createAnnouncement($company);
        $this->createAnnouncement(Company::factory()->create());

        $response = $this->actingAs($employee)->getJson('/v1/employee/announcements');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta']);

        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($announcement->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_employee_can_view_single_announcement(): void
    {
        $company = Company::factory()->create();
        $employee = $this->createUser($company, 'employee');
        $announcement = $this->createAnnouncement($company);

        $response = $this->actingAs($employee)->getJson("/v1/employee/announcements/{$announcement->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $announcement->id);
    }

    public function test_employee_can_get_pending_count(): void
    {
        $company = Company::factory()->create();
        $employee = $this->createUser($company, 'employee');
        $this->createAnnouncement($company);
        $this->createAnnouncement($company);

        $response = $this->actingAs($employee)->getJson('/v1/employee/announcements/pending-count');

        $response->assertOk()
            ->assertJsonPath('count', 2);
    }

    public function test_employee_can_mark_announcement_as_seen(): void
    {
        $company = Company::factory()->create();
        $employee = $this->createUser($company, 'employee');
        $announcement = $this->createAnnouncement($company);

        $response = $this->actingAs($employee)->postJson("/v1/employee/announcements/{$announcement->id}/seen");

        $response->assertOk()
            ->assertJsonPath('status', 'seen');

        $this->assertDatabaseHas('announcement_reads', [
            'announcement_id' => $announcement->id,
            'user_id' => $employee->id,
        ]);
    }

    public function test_employee_cannot_see_announcement_from_other_company(): void
    {
        $employee = $this->createUser(Company::factory()->create(), 'employee');
        $otherAnnouncement = $this->createAnnouncement(Company::factory()->create());

        $this->actingAs($employee)
            ->getJson("/v1/employee/announcements/{$otherAnnouncement->id}")
            ->assertForbidden();
    }

    private function createAnnouncement(Company $company): Announcement
    {
        $creator = User::factory()->create(['company_id' => $company->id]);

        return Announcement::create([
            'company_id' => $company->id,
            'created_by' => $creator->id,
            'title' => 'Anúncio de teste',
            'type' => 'general',
            'sent_at' => now()->subMinute(),
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
