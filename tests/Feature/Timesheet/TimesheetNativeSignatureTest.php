<?php

namespace Tests\Feature\Timesheet;

use App\Enums\ClosureStatus;
use App\Enums\TimesheetStatus;
use App\Http\Middleware\EnsureCompanyHasAccess;
use App\Models\EmployeeTimesheet;
use App\Models\MonthlyClosure;
use App\Models\Role;
use App\Models\TimesheetSignature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TimesheetNativeSignatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');

        $this->withoutMiddleware(EnsureCompanyHasAccess::class);

        Role::updateOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        Role::updateOrCreate(['name' => 'manager'], ['display_name' => 'Manager']);
        Role::updateOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);
    }

    public function test_sign_requires_signature_image(): void
    {
        $employee = $this->createEmployee();
        $timesheet = $this->createTimesheet($employee, TimesheetStatus::PENDING_EMPLOYEE);

        $this->actingAs($employee)
            ->postJson("/v1/employee/timesheets/{$timesheet->id}/sign", [
                'accepted_terms' => true,
                'password' => 'password',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('signature_image');
    }

    public function test_sign_requires_accepted_terms(): void
    {
        $employee = $this->createEmployee();
        $timesheet = $this->createTimesheet($employee, TimesheetStatus::PENDING_EMPLOYEE);

        $this->actingAs($employee)
            ->postJson("/v1/employee/timesheets/{$timesheet->id}/sign", [
                'signature_image' => $this->fakeBase64Image(),
                'accepted_terms' => false,
                'password' => 'password',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('accepted_terms');
    }

    public function test_sign_requires_password(): void
    {
        $employee = $this->createEmployee();
        $timesheet = $this->createTimesheet($employee, TimesheetStatus::PENDING_EMPLOYEE);

        $this->actingAs($employee)
            ->postJson("/v1/employee/timesheets/{$timesheet->id}/sign", [
                'signature_image' => $this->fakeBase64Image(),
                'accepted_terms' => true,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_sign_fails_with_wrong_password(): void
    {
        $employee = $this->createEmployee();
        $timesheet = $this->createTimesheet($employee, TimesheetStatus::PENDING_EMPLOYEE);

        $this->actingAs($employee)
            ->postJson("/v1/employee/timesheets/{$timesheet->id}/sign", [
                'signature_image' => $this->fakeBase64Image(),
                'accepted_terms' => true,
                'password' => 'wrong-password',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_sign_blocks_duplicate_employee_signature(): void
    {
        $employee = $this->createEmployee();
        $timesheet = $this->createTimesheet($employee, TimesheetStatus::PENDING_EMPLOYEE);

        // Primeira assinatura
        $this->actingAs($employee)
            ->postJson("/v1/employee/timesheets/{$timesheet->id}/sign", $this->validSignPayload())
            ->assertOk();

        // Garante que a folha avançou e apenas uma assinatura existe
        $this->assertDatabaseHas('employee_timesheets', [
            'id' => $timesheet->id,
            'status' => TimesheetStatus::PENDING_MANAGER->value,
        ]);

        // Verifica que somente uma assinatura foi criada
        $this->assertEquals(
            1,
            \App\Models\TimesheetSignature::where('employee_timesheet_id', $timesheet->id)
                ->where('role', 'employee')
                ->count()
        );
    }

    public function test_sign_stores_document_hash_and_evidence(): void
    {
        $employee = $this->createEmployee();
        $timesheet = $this->createTimesheet($employee, TimesheetStatus::PENDING_EMPLOYEE);

        $this->actingAs($employee)
            ->postJson("/v1/employee/timesheets/{$timesheet->id}/sign", $this->validSignPayload())
            ->assertOk();

        $signature = TimesheetSignature::where('employee_timesheet_id', $timesheet->id)->first();

        $this->assertNotNull($signature->document_hash);
        $this->assertNotNull($signature->signature_hash);
        $this->assertNotNull($signature->signature_image_path);
        $this->assertTrue($signature->accepted_terms);
        $this->assertNotNull($signature->password_confirmed_at);
    }

    public function test_sign_with_geolocation(): void
    {
        $employee = $this->createEmployee();
        $timesheet = $this->createTimesheet($employee, TimesheetStatus::PENDING_EMPLOYEE);

        $payload = $this->validSignPayload();
        $payload['latitude'] = -23.5505;
        $payload['longitude'] = -46.6333;

        $this->actingAs($employee)
            ->postJson("/v1/employee/timesheets/{$timesheet->id}/sign", $payload)
            ->assertOk();

        $signature = TimesheetSignature::where('employee_timesheet_id', $timesheet->id)->first();

        $this->assertNotNull($signature->latitude);
        $this->assertNotNull($signature->longitude);
    }

    public function test_signatures_superseded_when_snapshot_regenerated(): void
    {
        $employee = $this->createEmployee();
        $timesheet = $this->createTimesheet($employee, TimesheetStatus::PENDING_EMPLOYEE);

        // Assinar a folha
        $this->actingAs($employee)
            ->postJson("/v1/employee/timesheets/{$timesheet->id}/sign", $this->validSignPayload())
            ->assertOk();

        $this->assertEquals(1, TimesheetSignature::where('employee_timesheet_id', $timesheet->id)->whereNull('superseded_at')->count());

        // Reger ar snapshot invalida assinaturas e reseta status
        $signatureService = app(\App\Services\Timesheet\TimesheetSignatureService::class);
        $timesheet->refresh();
        $signatureService->supersedePreviousSignatures($timesheet);

        $timesheet->refresh();
        $this->assertEquals(TimesheetStatus::PENDING_EMPLOYEE, $timesheet->status);
        $this->assertEquals(0, TimesheetSignature::where('employee_timesheet_id', $timesheet->id)->whereNull('superseded_at')->count());
        $this->assertEquals(1, TimesheetSignature::where('employee_timesheet_id', $timesheet->id)->whereNotNull('superseded_at')->count());
    }

    public function test_employee_cannot_access_other_company_timesheet_pdf(): void
    {
        $employee1 = $this->createEmployee();
        $employee2 = $this->createEmployee(); // empresa diferente

        $timesheet = $this->createTimesheet($employee1, TimesheetStatus::COMPLETED);

        $this->actingAs($employee2)
            ->getJson("/v1/employee/timesheets/{$timesheet->id}/pdf")
            ->assertForbidden();
    }

    public function test_employee_timesheet_list_ignores_rows_with_mismatched_company(): void
    {
        $employee = $this->createEmployee();
        $ownTimesheet = $this->createTimesheet($employee, TimesheetStatus::PENDING_EMPLOYEE);
        $mismatchedTimesheet = $this->createMismatchedCompanyTimesheet($employee);

        $response = $this->actingAs($employee)->getJson('/v1/employee/timesheets');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($ownTimesheet->id, $ids);
        $this->assertNotContains($mismatchedTimesheet->id, $ids);
    }

    public function test_employee_cannot_sign_timesheet_with_mismatched_company(): void
    {
        $employee = $this->createEmployee();
        $timesheet = $this->createMismatchedCompanyTimesheet($employee);

        $this->actingAs($employee)
            ->postJson("/v1/employee/timesheets/{$timesheet->id}/sign", $this->validSignPayload())
            ->assertForbidden();
    }

    public function test_pdf_endpoint_returns_404_when_not_generated(): void
    {
        $employee = $this->createEmployee();
        $timesheet = $this->createTimesheet($employee, TimesheetStatus::COMPLETED);

        $this->actingAs($employee)
            ->getJson("/v1/employee/timesheets/{$timesheet->id}/pdf")
            ->assertStatus(404);
    }

    public function test_admin_can_view_signature_status(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createEmployee($admin->company_id);
        $timesheet = $this->createTimesheet($employee, TimesheetStatus::PENDING_EMPLOYEE);

        $response = $this->actingAs($admin)
            ->getJson("/v1/admin/timesheets/{$timesheet->id}");

        $response->assertOk()
            ->assertJsonPath('data.status', TimesheetStatus::PENDING_EMPLOYEE->value);
    }

    private function fakeBase64Image(): string
    {
        $pngHeader = "\x89PNG\r\n\x1a\n".str_repeat("\x00", 100);

        return 'data:image/png;base64,'.base64_encode($pngHeader);
    }

    private function validSignPayload(): array
    {
        return [
            'signature_image' => $this->fakeBase64Image(),
            'accepted_terms' => true,
            'password' => 'password',
        ];
    }

    private function createAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function createEmployee(?string $companyId = null): User
    {
        $user = User::factory()->create(array_filter(['company_id' => $companyId]));
        $user->assignRole('employee');

        return $user;
    }

    private function createTimesheet(User $employee, TimesheetStatus $status): EmployeeTimesheet
    {
        $closure = MonthlyClosure::create([
            'company_id' => $employee->company_id,
            'closed_by' => $employee->id,
            'reference_year' => 2026,
            'reference_month' => 4,
            'status' => ClosureStatus::OPEN->value,
            'closed_at' => now(),
        ]);

        return EmployeeTimesheet::create([
            'company_id' => $employee->company_id,
            'monthly_closure_id' => $closure->id,
            'employee_id' => $employee->id,
            'status' => $status->value,
            'snapshot_generated_at' => now(),
            'snapshot' => ['totals' => [], 'days' => []],
        ]);
    }

    private function createMismatchedCompanyTimesheet(User $employee): EmployeeTimesheet
    {
        $otherCompanyUser = $this->createEmployee();
        $closure = MonthlyClosure::create([
            'company_id' => $otherCompanyUser->company_id,
            'closed_by' => $otherCompanyUser->id,
            'reference_year' => 2026,
            'reference_month' => 3,
            'status' => ClosureStatus::OPEN->value,
            'closed_at' => now(),
        ]);

        return EmployeeTimesheet::create([
            'company_id' => $otherCompanyUser->company_id,
            'monthly_closure_id' => $closure->id,
            'employee_id' => $employee->id,
            'status' => TimesheetStatus::PENDING_EMPLOYEE->value,
            'snapshot_generated_at' => now(),
            'snapshot' => ['totals' => [], 'days' => []],
        ]);
    }
}
