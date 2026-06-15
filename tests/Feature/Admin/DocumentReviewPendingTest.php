<?php

namespace Tests\Feature\Admin;

use App\Enums\TimesheetStatus;
use App\Http\Middleware\EnsureCompanyHasAccess;
use App\Models\Absence;
use App\Models\Area;
use App\Models\Document;
use App\Models\EmployeeTimesheet;
use App\Models\MonthlyClosure;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentReviewPendingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureCompanyHasAccess::class);

        Role::updateOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        Role::updateOrCreate(['name' => 'manager'], ['display_name' => 'Manager']);
        Role::updateOrCreate(['name' => 'area_manager'], ['display_name' => 'Area Manager']);
        Role::updateOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);
    }

    public function test_pending_includes_documents_and_timesheets_awaiting_manager_signature(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createEmployee($admin->company_id);

        $document = $this->createDocument($employee, Document::STATUS_PENDING);
        $pendingManagerTimesheet = $this->createTimesheet($employee, TimesheetStatus::PENDING_MANAGER);
        $this->createTimesheet($employee, TimesheetStatus::PENDING_EMPLOYEE);
        $this->createTimesheet($employee, TimesheetStatus::COMPLETED);

        $response = $this->actingAs($admin)->getJson('/v1/admin/documents/pending');

        $response->assertOk();

        $types = collect($response->json('data'))->map(fn ($item) => [$item['type'], $item['id']]);

        $this->assertTrue($types->contains(['document', $document->id]));
        $this->assertTrue($types->contains(['timesheet_signature', $pendingManagerTimesheet->id]));
        $this->assertCount(2, $types);

        $timesheetItem = collect($response->json('data'))->firstWhere('id', $pendingManagerTimesheet->id);
        $this->assertSame(TimesheetStatus::PENDING_MANAGER->value, $timesheetItem['status']);
        $this->assertSame((string) $employee->id, $timesheetItem['employee']['id']);
    }

    public function test_pending_document_linked_to_absence_includes_absence_context(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createEmployee($admin->company_id);

        $absence = Absence::create([
            'company_id' => $employee->company_id,
            'user_id' => $employee->id,
            'type' => Absence::TYPE_SICK_LEAVE,
            'coverage_type' => Absence::COVERAGE_FULL_DAY,
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-11',
            'status' => Absence::STATUS_PENDING,
            'counts_for_accrual' => true,
            'created_by' => $employee->id,
        ]);

        $document = $this->createDocument($employee, Document::STATUS_PENDING, Document::CATEGORY_PERSONAL);
        $absence->documents()->attach($document->id);

        $response = $this->actingAs($admin)->getJson('/v1/admin/documents/pending');

        $response->assertOk();

        $item = collect($response->json('data'))->firstWhere('id', $document->id);

        $this->assertNotNull($item['absence']);
        $this->assertSame($absence->id, $item['absence']['id']);
        $this->assertSame(Absence::TYPE_SICK_LEAVE, $item['absence']['type']);
        $this->assertSame(Absence::STATUS_PENDING, $item['absence']['status']);
        $this->assertSame('2026-04-10', $item['absence']['start_date']);
        $this->assertSame('2026-04-11', $item['absence']['end_date']);
    }

    public function test_pending_does_not_leak_data_from_other_companies(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createEmployee($admin->company_id);
        $this->createDocument($employee, Document::STATUS_PENDING);
        $this->createTimesheet($employee, TimesheetStatus::PENDING_MANAGER);

        $otherAdmin = $this->createAdmin();
        $otherEmployee = $this->createEmployee($otherAdmin->company_id);
        $otherDocument = $this->createDocument($otherEmployee, Document::STATUS_PENDING);
        $otherTimesheet = $this->createTimesheet($otherEmployee, TimesheetStatus::PENDING_MANAGER);

        $response = $this->actingAs($admin)->getJson('/v1/admin/documents/pending');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertFalse($ids->contains((string) $otherDocument->id));
        $this->assertFalse($ids->contains((string) $otherTimesheet->id));
    }

    public function test_area_manager_only_sees_timesheets_for_managed_area(): void
    {
        $admin = $this->createAdmin();
        $managedArea = Area::factory()->create(['company_id' => $admin->company_id]);
        $otherArea = Area::factory()->create(['company_id' => $admin->company_id]);

        $manager = User::factory()->create(['company_id' => $admin->company_id]);
        $manager->syncRoles(['area_manager']);
        $manager->managedAreas()->attach($managedArea->id, [
            'id' => (string) Str::uuid(),
            'company_id' => $admin->company_id,
        ]);

        $managedEmployee = $this->createEmployee($admin->company_id, ['area_id' => $managedArea->id]);
        $otherEmployee = $this->createEmployee($admin->company_id, ['area_id' => $otherArea->id]);

        $visibleTimesheet = $this->createTimesheet($managedEmployee, TimesheetStatus::PENDING_MANAGER);
        $hiddenTimesheet = $this->createTimesheet($otherEmployee, TimesheetStatus::PENDING_MANAGER);

        $response = $this->actingAs($manager)->getJson('/v1/admin/documents/pending');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($visibleTimesheet->id));
        $this->assertFalse($ids->contains($hiddenTimesheet->id));
    }

    public function test_category_filter_excludes_timesheet_signature_items(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createEmployee($admin->company_id);

        $document = $this->createDocument($employee, Document::STATUS_PENDING, Document::CATEGORY_PAYROLL);
        $timesheet = $this->createTimesheet($employee, TimesheetStatus::PENDING_MANAGER);

        $response = $this->actingAs($admin)->getJson('/v1/admin/documents/pending?category='.Document::CATEGORY_PAYROLL);

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($document->id));
        $this->assertFalse($ids->contains($timesheet->id));
    }

    private function createAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        return $admin;
    }

    private function createEmployee(string $companyId, array $overrides = []): User
    {
        $employee = User::factory()->create(array_merge(['company_id' => $companyId], $overrides));
        $employee->syncRoles(['employee']);

        return $employee;
    }

    private function createDocument(User $employee, string $status, string $category = Document::CATEGORY_OTHERS): Document
    {
        return Document::create([
            'company_id' => $employee->company_id,
            'user_id' => $employee->id,
            'title' => 'Documento de teste',
            'category' => $category,
            'status' => $status,
            'mime_type' => 'application/pdf',
            'ext' => 'pdf',
            'size_bytes' => 1024,
            'path' => 'fake/path.pdf',
            'storage_disk' => 's3',
            'original_name' => 'documento.pdf',
            'uploaded_by' => $employee->id,
        ]);
    }

    private function createTimesheet(User $employee, TimesheetStatus $status): EmployeeTimesheet
    {
        $closure = MonthlyClosure::create([
            'company_id' => $employee->company_id,
            'closed_by' => $employee->id,
            'reference_year' => 2026,
            'reference_month' => 4,
            'status' => 'open',
            'closed_at' => now(),
        ]);

        return EmployeeTimesheet::create([
            'company_id' => $employee->company_id,
            'monthly_closure_id' => $closure->id,
            'employee_id' => $employee->id,
            'status' => $status->value,
            'snapshot_generated_at' => now(),
            'snapshot' => ['totals' => []],
        ]);
    }
}
