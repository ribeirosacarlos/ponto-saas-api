<?php

namespace Tests\Feature\Documents;

use App\Http\Middleware\EnsureCompanyHasAccess;
use App\Models\Document;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentCrossUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureCompanyHasAccess::class);
        Role::updateOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);
        Role::updateOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        Role::updateOrCreate(['name' => 'manager'], ['display_name' => 'Manager']);
    }

    public function test_employee_does_not_see_other_employees_documents_same_company(): void
    {
        $employeeA = User::factory()->create();
        $employeeA->syncRoles(['employee']);

        $employeeB = User::factory()->create(['company_id' => $employeeA->company_id]);
        $employeeB->syncRoles(['employee']);

        $docA = Document::create([
            'company_id' => $employeeA->company_id,
            'user_id' => $employeeA->id,
            'title' => 'Doc A',
            'category' => Document::CATEGORY_PERSONAL,
            'status' => Document::STATUS_AVAILABLE,
            'mime_type' => 'application/pdf',
            'ext' => 'pdf',
            'size_bytes' => 100,
            'path' => 'a.pdf',
            'storage_disk' => 'local',
            'original_name' => 'a.pdf',
            'uploaded_by' => $employeeA->id,
        ]);

        $docB = Document::create([
            'company_id' => $employeeB->company_id,
            'user_id' => $employeeB->id,
            'title' => 'Doc B',
            'category' => Document::CATEGORY_PERSONAL,
            'status' => Document::STATUS_AVAILABLE,
            'mime_type' => 'application/pdf',
            'ext' => 'pdf',
            'size_bytes' => 100,
            'path' => 'b.pdf',
            'storage_disk' => 'local',
            'original_name' => 'b.pdf',
            'uploaded_by' => $employeeB->id,
        ]);

        Sanctum::actingAs($employeeA, ['*']);

        $response = $this->getJson('/v1/documents?page=1');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($docA->id, $ids);
        $this->assertNotContains($docB->id, $ids);
    }

    public function test_admin_with_employee_role_only_sees_own_documents_on_employee_route(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['admin', 'employee']);

        $employee = User::factory()->create(['company_id' => $admin->company_id]);
        $employee->syncRoles(['employee']);

        $docAdmin = Document::create([
            'company_id' => $admin->company_id,
            'user_id' => $admin->id,
            'title' => 'Doc Admin',
            'category' => Document::CATEGORY_PERSONAL,
            'status' => Document::STATUS_AVAILABLE,
            'mime_type' => 'application/pdf',
            'ext' => 'pdf',
            'size_bytes' => 100,
            'path' => 'admin.pdf',
            'storage_disk' => 'local',
            'original_name' => 'admin.pdf',
            'uploaded_by' => $admin->id,
        ]);

        $docEmployee = Document::create([
            'company_id' => $employee->company_id,
            'user_id' => $employee->id,
            'title' => 'Doc Employee',
            'category' => Document::CATEGORY_PERSONAL,
            'status' => Document::STATUS_AVAILABLE,
            'mime_type' => 'application/pdf',
            'ext' => 'pdf',
            'size_bytes' => 100,
            'path' => 'employee.pdf',
            'storage_disk' => 'local',
            'original_name' => 'employee.pdf',
            'uploaded_by' => $employee->id,
        ]);

        Sanctum::actingAs($admin, ['*']);

        $response = $this->getJson('/v1/documents?page=1');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($docAdmin->id, $ids);
        $this->assertNotContains($docEmployee->id, $ids);
    }
}
